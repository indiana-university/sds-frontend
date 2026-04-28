#!/usr/bin/env bash
set -e

# Move to the project root directory (sds-frontend)
cd "$(dirname "$0")/../.."

echo "Building Omeka S Docker image for Kubernetes..."
# Build the image locally so OrbStack's Kubernetes can pull it (imagePullPolicy: IfNotPresent)
docker build -t omekas:latest -f docker/Dockerfile .

echo "Creating ConfigMap from docker/.development.env..."
kubectl create configmap omekas-env --from-env-file=docker/.development.env -o yaml --dry-run=client | kubectl apply -f -

echo "Applying MySQL manifests..."
kubectl apply -f docker/k8s/mysql.yaml

echo "Applying Omeka S manifests..."
kubectl apply -f docker/k8s/omekas.yaml

echo "Waiting for deployments to roll out..."
kubectl rollout status deployment/omekasmysql
kubectl rollout status deployment/omekas

echo "--------------------------------------------------------"
echo "Deployment successful!"
echo "Omeka S is starting up."
echo "Since you are using OrbStack, the LoadBalancer service will be mapped to localhost."
echo "Access Omeka S here: http://localhost:8000"
echo ""
echo "To view the logs for Omeka S, run:"
echo "kubectl logs -f deployment/omekas"
echo "--------------------------------------------------------"
