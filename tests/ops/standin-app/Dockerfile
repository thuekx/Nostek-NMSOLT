# Ops integration test only: stand-in for the NMSOLT app image.
ARG BASE=php:8.3-fpm-bookworm@sha256:6b4641a875dd3e0f7551b2751e1f5909084856d18166af7fbb522ac230feb702
FROM ${BASE}
ARG STANDIN_BROKEN=0
COPY router.php /opt/standin/router.php
COPY supervisorctl /usr/local/bin/supervisorctl
RUN chmod 0755 /usr/local/bin/supervisorctl \
    && if [ "${STANDIN_BROKEN}" = 1 ]; then touch /opt/standin/BROKEN; fi
ENTRYPOINT ["php", "-S", "0.0.0.0:80", "/opt/standin/router.php"]
