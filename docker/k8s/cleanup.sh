#!/usr/bin/env bash
set -e

# Move to the directory containing the Kubernetes manifests
cd "$(dirname "$0")"

echo "Cleaning up Omeka S Kubernetes deployment..."

# Delete the resources defined in the manifests
kubectl delete -f omekas.yaml --ignore-not-found -n sdsfrontend
kubectl delete -f mysql.yaml --ignore-not-found -n sdsfrontend
kubectl delete configmap omekas-env -n sdsfrontend --ignore-not-found

echo "--------------------------------------------------------"
echo "Cleanup complete!"
echo "All Omeka S deployments, services, configmaps, and volumes have been removed."
echo "--------------------------------------------------------"
