FROM ngrok/ngrok:alpine

# Switch to root to install packages
USER root

# Install required tools (alpine-based image)
RUN apk add --no-cache \
    bash \
    curl \
    jq \
    gettext \
    sed

# Create ngrok directory
RUN mkdir -p /ngrok && chmod 755 /ngrok

# Stay as root since we need to write to mounted volumes
# The ngrok user doesn't have permissions to modify .env files

ENTRYPOINT ["/ngrok/entrypoint.sh"]
