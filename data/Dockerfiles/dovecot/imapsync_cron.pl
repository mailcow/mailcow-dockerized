#!/usr/bin/perl

use DBI;
use File::Temp qw/ mkstemp /;
use LockFile::Simple qw(lock trylock unlock);
use Data::Dumper qw(Dumper);
use IPC::Run 'run';
use String::Util 'trim';

$DBNAME = '';
$DBUSER = '';
$DBPASS = '';

$run_dir="/tmp";
$dsn = "DBI:mysql:database=" . $DBNAME . ";host=mysql";
$lock_file = $run_dir . "/imapsync_busy";
$lockmgr = LockFile::Simple->make(-autoclean => 1, -max => 1);
$lockmgr->lock($lock_file) || die "can't lock ${lock_file}";
$dbh = DBI->connect($dsn, $DBUSER, $DBPASS);
open my $file, '<', "/etc/sogo/sieve.creds"; 
my $creds = <$file>; 
close $file;
my ($master_user, $master_pass) = split /:/, $creds;
my $sth = $dbh->prepare("SELECT id, user1, user2, host1, authmech1, password1, exclude, port1, enc1, delete2duplicates, maxage, subfolder2 FROM imapsync WHERE active = 1 AND (UNIX_TIMESTAMP(NOW()) - UNIX_TIMESTAMP(last_run) > mins_interval * 60 OR last_run IS NULL)");
$sth->execute();
my $row;

while ($row = $sth->fetchrow_arrayref()) {

  $id                 = @$row[0];
  $user1              = @$row[1];
  $user2              = @$row[2];
  $host1              = @$row[3];
  $authmech1          = @$row[4];
  $password1          = @$row[5];
  $exclude            = @$row[6];
  $port1              = @$row[7];
  $enc1               = @$row[8];
  $delete2duplicates  = @$row[9];
  $maxage             = @$row[10];
  $subfolder2         = @$row[11];

  if ($enc1 eq "TLS") { $enc1 = "--tls1"; } elsif ($enc1 eq "SSL") { $enc1 = "--ssl1"; } else { undef $enc1; }

  run [ "/usr/local/bin/imapsync",
	"--timeout1", "10",
	"--tmpdir", "/tmp",
	"--subscribeall",
	($exclude			eq ""	? () : ("--exclude", $exclude)),
	($subfolder2		eq ""	? () : ('--subfolder2', $subfolder2)),
	($maxage			eq "0"	? () : ('--maxage', $maxage)),
	($delete2duplicates	ne "1"	? () : ('--delete2duplicates')),
	(!defined($enc1)			? () : ($enc1)),
	"--host1", $host1,
	"--user1", $user1,
	"--password1", $password1,
	"--port1", $port1,
	"--host2", "localhost",
	"--user2", $user2 . '*' . trim($master_user),
	"--password2", trim($master_pass),
	'--no-modulesversion'], ">", \my $stdout;

  $update = $dbh->prepare("UPDATE imapsync SET returned_text = ?, last_run = NOW() WHERE id = ?");
  $update->bind_param( 1, ${stdout} );
  $update->bind_param( 2, ${id} );
  $update->execute();
}

$sth->finish();
$dbh->disconnect();

$lockmgr->unlock($lock_file);
