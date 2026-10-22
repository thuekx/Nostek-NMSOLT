# Nostek-NMSOLT GenieACS image (with-acs profile). Base pinned by digest.
FROM node:22-bookworm-slim@sha256:de170b39439eb0e5a3444ad7d60b015614a381e10fecae2e30ec9e735122f38d
RUN npm install --global --omit=dev genieacs@1.2.16 \
    && npm cache clean --force
COPY docker/genieacs/health.js /usr/local/lib/nos-genieacs-health.js
USER node
CMD ["genieacs-cwmp"]
