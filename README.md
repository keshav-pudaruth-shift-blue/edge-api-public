To build:

docker build -t keshavpudaruthshift/shift-edge:0.1.0.5-prod --build-arg VERSION=0.1.0 --build-arg ENVIRONMENT=production --build-arg BASE_IMAGE_VERSION=base-1.0.0-php-8-1-alpine --build-arg DOMAIN=edge-api.shift.blue -f docker/Dockerfile .

docker push keshavpudaruthshift/shift-edge:0.1.0.5-prod

kubectl set image deployment/edge-api edge-api=keshavpudaruthshift/shift-edge:0.1.0.5-prod
