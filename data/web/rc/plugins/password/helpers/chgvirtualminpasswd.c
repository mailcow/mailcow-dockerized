#include <stdio.h>
#include <unistd.h>

// set the UID this script will run as (root user)
#define UID 0
#define CMD "/usr/sbin/virtualmin"

/* INSTALLING:
  gcc -o chgvirtualminpasswd chgvirtualminpasswd.c
  chown root.apache chgvirtualminpasswd
  strip chgvirtualminpasswd
  chmod 4550 chgvirtualminpasswd
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
