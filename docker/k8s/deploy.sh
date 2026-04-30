#!/usr/bin/env bash
set -e

# Move to the project root directory (sds-frontend)
cd "$(dirname "$0")/../.."

echo "Building Omeka S Docker image for Kubernetes..."
# Build the image locally so OrbStack's Kubernetes can pull it (imagePullPolicy: IfNotPresent)
docker build -t omekas:latest -f docker/Dockerfile .

echo "Creating namespace 'sdsfrontend'..."
kubectl create namespace sdsfrontend --dry-run=client -o yaml | kubectl apply -f -

echo "Creating ConfigMap from docker/.sdsdb_dev.env..."
kubectl create configmap omekas-env --namespace=sdsfrontend --from-env-file=docker/.sdsdb_dev.env -o yaml --dry-run=client | kubectl apply -n sdsfrontend -f -

echo "Applying Omeka S manifests..."
kubectl apply -f docker/k8s/omekas.yaml

echo "Waiting for deployments to roll out..."
kubectl rollout status deployment/omekas -n sdsfrontend

echo "--------------------------------------------------------"
echo "Deployment successful!"
echo "Omeka S is starting up."
echo "Since you are using OrbStack, the LoadBalancer service will be mapped to localhost."
echo "Access Omeka S here: http://localhost:8000"
echo ""
echo "To view the logs for Omeka S, run:"
echo "kubectl logs -f deployment/omekas -n sdsfrontend"
echo "--------------------------------------------------------"
