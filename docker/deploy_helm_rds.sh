#!/usr/bin/env sh
set -euo pipefail

# ---------------------------------------------------------------------------
# Resolve the .env file relative to this script so it works from any cwd.
# Create .sdsrds.env by copying .sdsdb_dev.env and filling in the
# production credentials. It must NOT be committed to source control.
# ---------------------------------------------------------------------------
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
ENV_FILE="${SCRIPT_DIR}/.sdsrds.env"

if [ ! -f "$ENV_FILE" ]; then
  echo "ERROR: env file not found at ${ENV_FILE}" >&2
  echo "       Create it from .sdsdb_dev.env and fill in production credentials." >&2
  exit 1
fi

# Parse only the three secret values — no sourcing of the whole file,
# so non-secret vars are never leaked into the shell environment.
MYSQL_PASSWORD=$(grep '^MYSQL_PASSWORD=' "$ENV_FILE" | cut -d= -f2-)
OMEKA_ADMIN_PASSWORD=$(grep '^OMEKA_ADMIN_PASSWORD=' "$ENV_FILE" | cut -d= -f2-)
OIDC_CLIENT_SECRET=$(grep '^OIDC_CLIENT_SECRET=' "$ENV_FILE" | cut -d= -f2-)

read_optional_env() {
  grep "^$1=" "$ENV_FILE" | cut -d= -f2- || true
}

NAMESPACE="ua-vpit--research-technologies--rds"
RELEASE_NAME="sds"
HELM_TIMEOUT="${HELM_TIMEOUT:-30m}"
PVC_STORAGE_CLASS="${PVC_STORAGE_CLASS:-bl-sp-tkg-k8s-v2}"
STATUS_INTERVAL="${STATUS_INTERVAL:-20}"
ROLLOUT_TOKEN="${ROLLOUT_TOKEN:-$(date -u '+%Y%m%d%H%M%S')}"
IMAGE_REGISTRY_SERVER="${IMAGE_REGISTRY_SERVER:-registry.docker.iu.edu}"
IMAGE_PULL_SECRET="${IMAGE_PULL_SECRET:-}"
IMAGE_REGISTRY_USERNAME="${IMAGE_REGISTRY_USERNAME:-$(read_optional_env IMAGE_REGISTRY_USERNAME)}"
IMAGE_REGISTRY_PASSWORD="${IMAGE_REGISTRY_PASSWORD:-$(read_optional_env IMAGE_REGISTRY_PASSWORD)}"
IMAGE_REGISTRY_EMAIL="${IMAGE_REGISTRY_EMAIL:-$(read_optional_env IMAGE_REGISTRY_EMAIL)}"

# ---------------------------------------------------------------------------
# 1. Create the secret imperatively from the local env file when it is missing.
#    Credentials are never stored in Helm release history or shell history.
#    Key names must match what the Deployment's secretRef expects.
# ---------------------------------------------------------------------------
SECRET_OUT=$(kubectl create secret generic omekas-secrets \
  --namespace "$NAMESPACE" \
  --from-literal=MYSQL_PASSWORD="$MYSQL_PASSWORD" \
  --from-literal=OMEKA_ADMIN_PASSWORD="$OMEKA_ADMIN_PASSWORD" \
  --from-literal=OIDC_CLIENT_SECRET="$OIDC_CLIENT_SECRET" \
  2>&1) && echo "Secret created." || {
  if echo "$SECRET_OUT" | grep -q "already exists"; then
    echo "Secret already exists, skipping."
  else
    echo "ERROR creating secret: $SECRET_OUT" >&2
    exit 1
  fi
}

# ---------------------------------------------------------------------------
# 2. Create the registry pull secret when registry credentials are available.
#    Optional keys may be placed in .sdsrds.env, or supplied as shell env vars:
#      IMAGE_REGISTRY_USERNAME, IMAGE_REGISTRY_PASSWORD, IMAGE_REGISTRY_EMAIL
#    Or set IMAGE_PULL_SECRET to the name of an existing image pull secret.
# ---------------------------------------------------------------------------
USE_IMAGE_PULL_SECRET=false

if [ -n "$IMAGE_REGISTRY_USERNAME" ] && [ -n "$IMAGE_REGISTRY_PASSWORD" ]; then
  IMAGE_PULL_SECRET="${IMAGE_PULL_SECRET:-omekas-registry}"
  REGISTRY_SECRET_OUT=$(kubectl create secret docker-registry "$IMAGE_PULL_SECRET" \
    --namespace "$NAMESPACE" \
    --docker-server="$IMAGE_REGISTRY_SERVER" \
    --docker-username="$IMAGE_REGISTRY_USERNAME" \
    --docker-password="$IMAGE_REGISTRY_PASSWORD" \
    --docker-email="${IMAGE_REGISTRY_EMAIL:-unused@example.com}" \
    2>&1) && echo "Image pull secret created." || {
    if echo "$REGISTRY_SECRET_OUT" | grep -q "already exists"; then
      echo "Image pull secret already exists, reusing it."
    else
      echo "ERROR creating image pull secret: $REGISTRY_SECRET_OUT" >&2
      exit 1
    fi
  }
  USE_IMAGE_PULL_SECRET=true
elif [ -n "$IMAGE_PULL_SECRET" ]; then
  echo "Using existing image pull secret: ${IMAGE_PULL_SECRET}"
  USE_IMAGE_PULL_SECRET=true
else
  echo "WARNING: No image registry credentials found; image pull may fail for ${IMAGE_REGISTRY_SERVER}." >&2
  echo "         Set IMAGE_REGISTRY_USERNAME and IMAGE_REGISTRY_PASSWORD, add them to ${ENV_FILE}," >&2
  echo "         or set IMAGE_PULL_SECRET to an existing secret name." >&2
fi

# ---------------------------------------------------------------------------
# 3. Install / upgrade the Helm chart.
#    secrets.create=false tells the chart to skip rendering secret.yaml
#    because the secret is already managed externally (step 1 above).
#
#    This namespace does not grant this user read access to Kubernetes Secrets.
#    Helm stores release metadata in Secrets by default, so use ConfigMaps for
#    release storage and keep application credentials in the external secret.
# ---------------------------------------------------------------------------
export HELM_DRIVER=configmap
set -- \
  --set secrets.create=false \
  --set "pvc.storageClassName=${PVC_STORAGE_CLASS}" \
  --set-string "omekas.rolloutToken=${ROLLOUT_TOKEN}"

if [ "$USE_IMAGE_PULL_SECRET" = true ]; then
  set -- "$@" --set "omekas.imagePullSecrets[0].name=${IMAGE_PULL_SECRET}"
fi

echo "Using Helm release storage driver: ${HELM_DRIVER}"
echo "Using PVC storage class: ${PVC_STORAGE_CLASS}"
echo "Using rollout token: ${ROLLOUT_TOKEN}"
if [ "$USE_IMAGE_PULL_SECRET" = true ]; then
  echo "Using image pull secret: ${IMAGE_PULL_SECRET}"
fi

if ! kubectl get storageclass "$PVC_STORAGE_CLASS" >/dev/null 2>&1; then
  echo "WARNING: Could not verify StorageClass ${PVC_STORAGE_CLASS}. Continuing with Helm deploy." >&2
fi

print_deploy_status() {
  echo ""
  echo "Waiting for Helm resources at $(date '+%Y-%m-%d %H:%M:%S')..."
  kubectl get pvc "${RELEASE_NAME}-omekas-volume-pvc" -n "$NAMESPACE" 2>/dev/null || true
  kubectl get ingress "${RELEASE_NAME}-omekas" -n "$NAMESPACE" 2>/dev/null || true
  kubectl get endpoints "${RELEASE_NAME}-omekas" -n "$NAMESPACE" 2>/dev/null || true
  kubectl get deployment "${RELEASE_NAME}-omekas" -n "$NAMESPACE" 2>/dev/null || true
  kubectl get replicasets -n "$NAMESPACE" -l "app.kubernetes.io/instance=${RELEASE_NAME}" 2>/dev/null || true
  kubectl get pods -n "$NAMESPACE" -l "app.kubernetes.io/instance=${RELEASE_NAME}" -o wide 2>/dev/null || true
  kubectl get events -n "$NAMESPACE" --sort-by=.lastTimestamp 2>/dev/null | tail -n 8 || true
}

watch_deploy_status() {
  while :; do
    sleep "$STATUS_INTERVAL"
    print_deploy_status >&2
  done
}

stop_status_watcher() {
  if [ -n "${STATUS_PID:-}" ]; then
    kill "$STATUS_PID" 2>/dev/null || true
    wait "$STATUS_PID" 2>/dev/null || true
  fi
}

watch_deploy_status &
STATUS_PID=$!
trap stop_status_watcher EXIT INT TERM

if ! helm upgrade --install "$RELEASE_NAME" "${SCRIPT_DIR}/helm_rds" \
  --namespace "$NAMESPACE" \
  "$@" \
  --wait \
  --rollback-on-failure \
  --timeout "$HELM_TIMEOUT"; then
  stop_status_watcher
  echo "" >&2
  echo "Helm deploy failed. If the PVC is still Pending, inspect storage provisioning with:" >&2
  echo "  kubectl get pvc ${RELEASE_NAME}-omekas-volume-pvc -n ${NAMESPACE}" >&2
  echo "  kubectl describe pvc ${RELEASE_NAME}-omekas-volume-pvc -n ${NAMESPACE}" >&2
  echo "If the PVC is Bound but the Deployment is not progressing, inspect deployment events with:" >&2
  echo "  kubectl describe deployment ${RELEASE_NAME}-omekas -n ${NAMESPACE}" >&2
  echo "  kubectl get events -n ${NAMESPACE} --sort-by=.lastTimestamp" >&2
  echo "If the pod cannot pull the image, provide registry credentials with:" >&2
  echo "  IMAGE_REGISTRY_USERNAME=<username> IMAGE_REGISTRY_PASSWORD=<password> ${SCRIPT_DIR}/deploy_helm_rds.sh" >&2
  echo "If the app is running but the ingress returns Bad Gateway, inspect routing with:" >&2
  echo "  kubectl get ingress,endpoints,service ${RELEASE_NAME}-omekas -n ${NAMESPACE}" >&2
  echo "  kubectl describe ingress ${RELEASE_NAME}-omekas -n ${NAMESPACE}" >&2
  echo "  kubectl logs deployment/${RELEASE_NAME}-omekas -n ${NAMESPACE} --tail=100" >&2
  echo "To override the configured StorageClass, rerun this script with:" >&2
  echo "  PVC_STORAGE_CLASS=<storage-class> ${SCRIPT_DIR}/deploy_helm_rds.sh" >&2
  exit 1
fi

stop_status_watcher
