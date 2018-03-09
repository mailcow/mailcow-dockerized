#!/usr/bin/perl

use DBI;
use LockFile::Simple qw(lock trylock unlock);
use Proc::ProcessTable;
use Data::Dumper qw(Dumper);
use IPC::Run 'run';
use String::Util 'trim';
use File::Temp;

my $t = Proc::ProcessTable->new;
my $imapsync_running = grep { $_->{cmndline} =~ /^\/usr\/bin\/perl \/usr\/local\/bin\/imapsync\s/ } @{$t->table};
if ($imapsync_running eq 1)
{
  print "imapsync is active, exiting...";
  exit;
}

$DBNAME = '';
$DBUSER = '';
$DBPASS = '';

$run_dir="/tmp";
$dsn = "DBI:mysql:database=" . $DBNAME . ";host=mysql";
$lock_file = $run_dir . "/imapsync_busy";
$lockmgr = LockFile::Simple->make(-autoclean => 1, -max => 1);
$lockmgr->lock($lock_file) || die "can't lock ${lock_file}";
$dbh = DBI->connect($dsn, $DBUSER, $DBPASS, {
  mysql_auto_reconnect => 1,
  mysql_enable_utf8mb4 => 1
});
open my $file, '<', "/etc/sogo/sieve.creds"; 
my $creds = <$file>; 
close $file;
my ($master_user, $master_pass) = split /:/, $creds;
my $sth = $dbh->prepare("SELECT id, user1, user2, host1, authmech1, password1, exclude, port1, enc1, delete2duplicates, maxage, subfolder2, delete1, delete2, automap, skipcrossduplicates, maxbytespersecond FROM imapsync WHERE active = 1 AND is_running = 0 AND (UNIX_TIMESTAMP(NOW()) - UNIX_TIMESTAMP(last_run) > mins_interval * 60 OR last_run IS NULL) ORDER BY last_run");
$sth->execute();
my $row;

while ($row = $sth->fetchrow_arrayref()) {

  $id                  = @$row[0];
  $user1               = @$row[1];
  $user2               = @$row[2];
  $host1               = @$row[3];
  $authmech1           = @$row[4];
  $password1           = @$row[5];
  $exclude             = @$row[6];
  $port1               = @$row[7];
  $enc1                = @$row[8];
  $delete2duplicates   = @$row[9];
  $maxage              = @$row[10];
  $subfolder2          = @$row[11];
  $delete1             = @$row[12];
  $delete2             = @$row[13];
  $automap             = @$row[14];
  $skipcrossduplicates = @$row[15];
  $maxbytespersecond   = @$row[16];

  $is_running = $dbh->prepare("UPDATE imapsync SET is_running = 1 WHERE id = ?");
  $is_running->bind_param( 1, ${id} );
  $is_running->execute();

  if ($enc1 eq "TLS") { $enc1 = "--tls1"; } elsif ($enc1 eq "SSL") { $enc1 = "--ssl1"; } else { undef $enc1; }

  my $template = $run_dir . '/imapsync.XXXXXXX';
  my $passfile1 = File::Temp->new(TEMPLATE => $template);
  my $passfile2 = File::Temp->new(TEMPLATE => $template);

  print $passfile1 "$password1\n";
  print $passfile2 trim($master_pass) . "\n";

  run [ "/usr/local/bin/imapsync",
	"--timeout1", "10",
	"--tmpdir", "/tmp",
	"--subscribeall",
	"--nofoldersizes",
	"--skipsize",
	"--buffersize", "8192000",
	"--split1", "3000",
	"--split2", "3000",
	"--fastio1",
	"--fastio2",
	($exclude eq ""	? () : ("--exclude", $exclude)),
	($subfolder2 eq "" ? () : ('--subfolder2', $subfolder2)),
	($maxage eq "0" ? () : ('--maxage', $maxage)),
	($maxbytespersecond eq "0" ? () : ('--maxbytespersecond', $maxbytespersecond)),
	($delete2duplicates	ne "1" ? () : ('--delete2duplicates')),
	($delete1	ne "1" ? () : ('--delete')),
  ($delete2 ne "1" ? () : ('--delete2')),
  ($automap ne "1" ? () : ('--automap')),
  ($skipcrossduplicates ne "1" ? () : ('--skipcrossduplicates')),
	(!defined($enc1) ? () : ($enc1)),
	"--host1", $host1,
	"--user1", $user1,
	"--passfile1", $passfile1->filename,
	"--port1", $port1,
	"--host2", "localhost",
	"--user2", $user2 . '*' . trim($master_user),
	"--passfile2", $passfile2->filename,
	'--no-modulesversion'], ">", \my $stdout;

  $update = $dbh->prepare("UPDATE imapsync SET returned_text = ?, last_run = NOW(), is_running = 0 WHERE id = ?");
  $update->bind_param( 1, ${stdout} );
  $update->bind_param( 2, ${id} );
  $update->execute();
}

$sth->finish();
$dbh->disconnect();

$lockmgr->unlock($lock_file);
