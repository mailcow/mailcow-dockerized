FROM debian:jessie

RUN apt-get update
RUN apt-get install --no-install-recommends -y iptables ca-certificates wget
RUN apt-get autoremove -y
RUN rm -rf /var/lib/apt/lists/*

ADD rules.sh /rules.sh
ADD startup.sh /startup.sh

ENTRYPOINT ["/startup.sh"]