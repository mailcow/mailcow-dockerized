#include <stdio.h>
#include <unistd.h>

// set the UID this script will run as (cyrus user)
#define UID 96
// set the path to saslpasswd or saslpasswd2
#define CMD "/usr/sbin/saslpasswd2"

/* INSTALLING:
  gcc -o chgsaslpasswd chgsaslpasswd.c
  chown cyrus.apache chgsaslpasswd
  strip chgsaslpasswd
  chmod 4550 chgsaslpasswd
*/

main(int argc, char *argv[])
{
  int rc,cc;

  cc = setuid(UID);
  rc = execvp(CMD, argv);
  if ((rc != 0) || (cc != 0))
  {
    fprintf(stderr, "__ %s:  failed %d  %d\n", argv[0], rc, cc);
    return 1;
  }

  return 0;
}
