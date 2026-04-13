FROM nginx:1.25-alpine

# Remove default config
RUN rm /etc/nginx/conf.d/default.conf

# Bake our config directly into the image
COPY default.conf /etc/nginx/conf.d/default.conf

# Run as non-root for Podman rootless compatibility
RUN chown -R nginx:nginx /etc/nginx/conf.d