# ── Stage: Production ──────────────────────────────────────
FROM nginx:1.25-alpine

LABEL maintainer="UAS Server Administration"
LABEL description="Web Statis — CV/Portfolio (Nginx)"

# Hapus default config Nginx
RUN rm -rf /etc/nginx/conf.d/default.conf

# Copy custom Nginx config
COPY nginx.conf /etc/nginx/conf.d/default.conf

# Copy semua aset web statis
COPY . /usr/share/nginx/html/

# Hapus file non-web dari direktori HTML (Dockerfile, config, dll)
RUN rm -f /usr/share/nginx/html/Dockerfile \
          /usr/share/nginx/html/nginx.conf

# Expose port 80
EXPOSE 80

# Nginx berjalan di foreground
CMD ["nginx", "-g", "daemon off;"]
