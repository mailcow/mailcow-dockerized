FROM solr:7.7-slim

USER root

# renovate: datasource=github-releases depName=tianon/gosu versioning=semver-coerced extractVersion=(?<version>.*)$
ARG GOSU_VERSION=1.17

COPY solr.sh /
COPY solr-config-7.7.0.xml /
COPY solr-schema-7.7.0.xml /

RUN dpkgArch="$(dpkg --print-architecture | awk -F- '{ print $NF }')" \
  && wget -O /usr/local/bin/gosu "https://github.com/tianon/gosu/releases/download/$GOSU_VERSION/gosu-$dpkgArch" \
  && chmod +x /usr/local/bin/gosu \
  && gosu nobody true \
  && apt-get update && apt-get install -y --no-install-recommends \
  tzdata \
  curl \
  bash \
  zip \
  && apt-get autoclean \
  && rm -rf /var/lib/apt/lists/* \
  && chmod +x /solr.sh \
  && sync \
  && bash /solr.sh --bootstrap
  
RUN zip -q -d /opt/solr/server/lib/ext/log4j-core-*.jar org/apache/logging/log4j/core/lookup/JndiLookup.class

RUN apt remove zip -y  

CMD ["/solr.sh"]
