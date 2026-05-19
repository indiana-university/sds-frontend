#!/usr/bin/env sh
set -euo pipefail

# ---------------------------------------------------------------------------
# Resolve the .env file relative to this script so it works from any cwd
# ---------------------------------------------------------------------------
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
ENV_FILE="${SCRIPT_DIR}/.sdsdb_dev.env"

if [ ! -f "$ENV_FILE" ]; then
  echo "ERROR: env file not found at ${ENV_FILE}" >&2
  exit 1
fi

# Parse only the three secret values — no sourcing of the whole file,
# so non-secret vars are never leaked into the shell environment.
MYSQL_PASSWORD=$(grep '^MYSQL_PASSWORD=' "$ENV_FILE" | cut -d= -f2-)
OMEKA_ADMIN_PASSWORD=$(grep '^OMEKA_ADMIN_PASSWORD=' "$ENV_FILE" | cut -d= -f2-)
OIDC_CLIENT_SECRET=$(grep '^OIDC_CLIENT_SECRET=' "$ENV_FILE" | cut -d= -f2-)

# ---------------------------------------------------------------------------
# 1. Ensure the namespace exists before creating the secret
# ---------------------------------------------------------------------------
kubectl create namespace sdsfrontend --dry-run=client -o yaml | kubectl apply -f -

# ---------------------------------------------------------------------------
# 2. Create (or update) the secret imperatively from the local env file.
#    Credentials are never stored in Helm release history or shell history.
#    Key names must match what the Deployment's secretRef expects.
# ---------------------------------------------------------------------------
kubectl create secret generic omekas-secrets \
  --namespace sdsfrontend \
  --from-literal=MYSQL_PASSWORD="$MYSQL_PASSWORD" \
  --from-literal=OMEKA_ADMIN_PASSWORD="$OMEKA_ADMIN_PASSWORD" \
  --from-literal=OIDC_CLIENT_SECRET="$OIDC_CLIENT_SECRET" \
  --dry-run=client -o yaml | kubectl apply -f -

# ---------------------------------------------------------------------------
# 3. Install / upgrade the Helm chart.
#    secrets.create=false tells the chart to skip rendering secret.yaml
#    because the secret is already managed externally (step 2 above).
# ---------------------------------------------------------------------------
helm upgrade --install sds ./helm_local \
  --namespace sdsfrontend \
  --create-namespace \
  --set secrets.create=false
