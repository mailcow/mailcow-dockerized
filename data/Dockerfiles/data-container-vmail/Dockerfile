FROM debian:jessie

RUN groupadd -g 5000 vmail \
	&& useradd -g vmail -u 5000 vmail -d /var/vmail

RUN mkdir -p /var/vmail \
  && chown -R 5000:5000 /var/vmail
VOLUME /var/vmail
USER vmail
CMD ["echo", "Data container for mail"]
