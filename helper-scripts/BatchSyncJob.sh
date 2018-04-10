#!/bin/bash

Imapsync="docker run gilleslamiral/imapsync imapsync"
GetCredentials=`docker exec -it $(docker ps -qf name=dovecot-mailcow) cat /etc/sogo/sieve.creds`
TimeFormat="%Y-%m-%d %H:%M:%S"
LogFile=mailmigration.log #Default: LogFile=mailmigration.log

FromServer=             #Example: FromServer=mx.example1.com
ToServer=               #Example: ToServer=mx.example2.com
FromPort=               #Example: FromPort=993
ToPort=                 #Example: ToPort=143
CertFrom=               #Example: CertFrom=SSL
CertTo=                 #Example: CertTo=Tls
MasterUserFrom=         #Example MasterUserFrom=admin
MasterPassFrom=         #Example MasterPassFrom=asd123
MasterUserTo=`sed 's/:.*//' <<< $GetCredentials` #if this script is run on target Mailcow server, credentials will be found automaticaly
MasterPassTo=`sed 's/.*://' <<< $GetCredentials | tr -cd '[[:alnum:]]._-' `
#If not run on target Mailcow machine, uncomment these:
#MasterUserTo=
#MasterPassTo=


#Insert neccessary mailboxes below
#Each mailbox must be in a new line
#Example:
#       MailBoxes="info@email.com
#       admin@email.com
#       accounting@email.com
#       asd@email.com
#       123@email.com" #End of list
#
#
MailBoxes="" #End of list


## Loop to sync all mailboxes one after other.
for Mailbox in $MailBoxes
do
        MESSAGE="[`date +"$TimeFormat"`] synchronizing $Mailbox@$FromServer to $Mailbox@$ToServer ..."
        echo $MESSAGE
        echo $MESSAGE >> $LogFile


        $Imapsync\
                --host1 $FromServer \
                --host2 $ToServer \
                --user1 "$Mailbox"*$MasterUserFrom \
                --password1 $MasterPassFrom \
                --user2 "$Mailbox"*$MasterUserTo \
                --password2 $MasterPassTo \
                --syncinternaldates --"$CertFrom"1 \
                --"$CertTo"2 \
                --noauthmd5 \
                --split1 100 \
                --split2 100 \
                --port1 $FromPort \
                --port2 $ToPort \
                --allowsizemismatch \

        STATUS=$?

        if [ $STATUS -eq 0 ]
        then
        MESSAGE="[`date +"$TimeFormat"`] COMPLETED for $Mailbox@$FromServer to $Mailbox@$ToServer"
        echo $MESSAGE >> $LogFile
        else
        MESSAGE="[`date +"$TimeFormat"`] FAILED for $Mailbox@$FromServer to $Mailbox@$ToServer"
        echo $MESSAGE >> $LogFile
        Fails=$((Fails+1))
        fi
done

MESSAGE="[`date +"$TimeFormat"`] Script completed with $Fails errors, if necessary, please check $LogFile !"
echo $MESSAGE
echo $MESSAGE >> $LogFile
exit 0
