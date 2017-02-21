#!/usr/bin/perl
=pod
Script to change the LDAP password using the set_password method
to proper setting the password policy attributes
author: Zbigniew Szmyd (zbigniew.szmyd@linseco.pl)
version 1.0 2016-02-22
=cut

use Net::LDAP;
use Net::LDAP::Extension::SetPassword;
use URI;
use utf8;
binmode(STDOUT, ':utf8');

my %PAR = ();
if (my $param = shift @ARGV){
    print "Password change in LDAP\n\n";
    print "Run script without any parameter and pass the following data:\n";
    print "URI\nbaseDN\nFilter\nbindDN\nbindPW\nLogin\nuserPass\nnewPass\nCAfile\n";
    exit;
}

foreach my $param ('uri','base','filter','binddn','bindpw','user','pass','new_pass','ca'){
    $PAR{$param} = <>;
    $PAR{$param} =~ s/\r|\n//g;
}

my @servers = split (/\s+/, $PAR{'uri'});
my $active_server = 0;

my $ldap;
while ((my $serwer = shift @servers) && !($active_server)) {
    my $ldap_uri = URI->new($serwer);
    if ($ldap_uri->secure) {
        $ldap = Net::LDAP->new($ldap_uri->as_string,
            version => 3,
            verify  => 'require',
            sslversion => 'tlsv1',
            cafile  => $PAR{'ca'});
    } else {
        $ldap = Net::LDAP->new($ldap_uri->as_string, version => 3);
    }
    $active_server = 1 if ($ldap);
}

if ($active_server) {
    my $mesg = $ldap->bind($PAR{'binddn'}, password => $PAR{'bindpw'});
    if ($mesg->code != 0) {
        print "Cannot login: ". $mesg->error;
    } else {
        # Wyszukanie usera wg filtra
        $PAR{'filter'} =~ s/\%login/$PAR{'user'}/;
        my @search_args = (
            base => $PAR{'base'},
            scope  => 'sub',
            filter => $PAR{'filter'},
            attrs  => ['1.1'],
        );
        my $result = $ldap->search(@search_args);
        if ($result->code) {
            print $result->error;
        } else {
            my $count = $result->count;
            if ($count == 1) {
                my @users = $result->entries;
                my $dn = $users[0]->dn();
                $result = $ldap->bind($dn, password => $PAR{'pass'});
                if ($result->code){
                    print $result->error;
                } else {
                    $result = $ldap->set_password(newpasswd => $PAR{'new_pass'});
                    if ($result->code) {
                        print $result->error;
                    } else {
                        print "OK";
                    }
                }
            } else {
                print "User not found in LDAP\n" if $count == 0;
                print "Found $count users\n";
            }
        }
    }
    $ldap->unbind();
} else {
    print "Cannot connect to any server";
}
