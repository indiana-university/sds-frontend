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

NAMESPACE="ua-vpit--research-technologies--rds"

# ---------------------------------------------------------------------------
# 1. Create (or update) the secret imperatively from the local env file.
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
# 2. Install / upgrade the Helm chart.
#    secrets.create=false tells the chart to skip rendering secret.yaml
#    because the secret is already managed externally (step 1 above).
# ---------------------------------------------------------------------------
helm upgrade --install sds ./helm_rds \
  --namespace "$NAMESPACE" \
  --set secrets.create=false \
  --atomic \
  --timeout 120s
