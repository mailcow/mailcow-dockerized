FROM debian:jessie

RUN mkdir -p /data/dkim/keys \
	&& mkdir -p /data/dkim/txt \
	&& chown -R 33:33 /data/dkim
VOLUME /data/dkim
USER www-data
CMD ["echo", "Data container for dkim keys"]
