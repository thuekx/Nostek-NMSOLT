# LAB-ONLY staging build. Pin by SHA digest and scan before production.
FROM node:22-bookworm-slim
RUN npm install --global --omit=dev genieacs@1.2.16 \
    && npm cache clean --force
USER node
CMD ["genieacs-cwmp"]
