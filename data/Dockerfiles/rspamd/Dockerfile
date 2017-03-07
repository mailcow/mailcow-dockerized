FROM ubuntu:xenial
MAINTAINER Andre Peters <andre.peters@servercow.de>

ENV DEBIAN_FRONTEND noninteractive
ENV LC_ALL C

RUN dpkg-divert --local --rename --add /sbin/initctl \
    && ln -sf /bin/true /sbin/initctl \
    && dpkg-divert --local --rename --add /usr/bin/ischroot \
    && ln -sf /bin/true /usr/bin/ischroot

RUN apt-key adv --fetch-keys http://rspamd.com/apt-stable/gpg.key \
    && echo "deb http://rspamd.com/apt-stable/ xenial main" > /etc/apt/sources.list.d/rspamd.list \
    && apt-get update \
    && apt-get -y install rspamd ca-certificates python-pip

RUN echo '.include $LOCAL_CONFDIR/local.d/rspamd.conf.local' > /etc/rspamd/rspamd.conf.local

COPY settings.conf /etc/rspamd/modules.d/settings.conf
COPY antivirus.conf /etc/rspamd/modules.d/antivirus.conf

RUN pip install -U oletools

CMD /usr/bin/rspamd -f -u _rspamd -g _rspamd

RUN apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

USER _rspamd

EXPOSE 11333 11334
