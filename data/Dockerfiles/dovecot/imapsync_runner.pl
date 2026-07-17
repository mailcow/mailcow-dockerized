#!/usr/bin/perl

use DBI;
use LockFile::Simple qw(lock trylock unlock);
use Proc::ProcessTable;
use Data::Dumper qw(Dumper);
use IPC::Run 'run';
use File::Temp;
use Try::Tiny;
use sigtrap 'handler' => \&sig_handler, qw(INT TERM KILL QUIT);

sub trim { my $s = shift; $s =~ s/^\s+|\s+$//g; return $s };
my $t = Proc::ProcessTable->new;
my $imapsync_running = grep { $_->{cmndline} =~ /imapsync\s/i } @{$t->table};
if ($imapsync_running ge 1)
{
  print "imapsync is active, exiting...";
  exit;
}

sub qqw($) {
  my @params = ();
  my @values = split(/(?=--)/, $_[0]);
  foreach my $val (@values) {
    my @tmpparam = split(/ /, $val, 2);
    foreach my $tmpval (@tmpparam) {
        if ($tmpval ne '') {
          push @params, $tmpval;
        }
    }
  }
  foreach my $val (@params) {
    $val=trim($val);
  }
  return @params;
}

$run_dir="/tmp";
$dsn = 'DBI:mysql:database=' . $ENV{'DBNAME'} . ';mysql_socket=/var/run/mysqld/mysqld.sock';
$lock_file = $run_dir . "/imapsync_busy";
$lockmgr = LockFile::Simple->make(-autoclean => 1, -max => 1);
$lockmgr->lock($lock_file) || die "can't lock ${lock_file}";
$dbh = DBI->connect($dsn, $ENV{'DBUSER'}, $ENV{'DBPASS'}, {
  mysql_auto_reconnect => 1,
  mysql_enable_utf8mb4 => 1
});
$dbh->do("UPDATE imapsync SET is_running = 0");

sub sig_handler {
  # Send die to force exception in "run"
  die "sig_handler received signal, preparing to exit...\n";
};

open my $file, '<', "/etc/sogo/sieve.creds";
my $creds = <$file>;
close $file;
my ($master_user, $master_pass) = split /:/, $creds;
# Source-based schema: join imapsync_source to read host/port/encryption/auth-type
my $sth = $dbh->prepare("SELECT
  i.id,
  i.user1,
  i.user2,
  s.host1,
  s.auth_type,
  i.password1,
  i.exclude,
  s.port1,
  s.enc1,
  i.delete2duplicates,
  i.maxage,
  i.subfolder2,
  i.delete1,
  i.delete2,
  i.automap,
  i.skipcrossduplicates,
  i.maxbytespersecond,
  i.custom_params,
  i.subscribeall,
  i.timeout1,
  i.timeout2,
  i.dry,
  s.oauth_access_token,
  s.oauth_token_expires,
  s.oauth_flow,
  i.source_id
    FROM imapsync i JOIN imapsync_source s ON i.source_id = s.id
      WHERE i.active = 1
        AND s.active = 1
        AND i.is_running = 0
        AND (
          UNIX_TIMESTAMP(NOW()) - UNIX_TIMESTAMP(i.last_run) > i.mins_interval * 60
          OR
          i.last_run IS NULL)
  ORDER BY i.last_run");

$sth->execute();
my $row;

while ($row = $sth->fetchrow_arrayref()) {

  $id                  = @$row[0];
  $user1               = @$row[1];
  $user2               = @$row[2];
  $host1               = @$row[3];
  $auth_type           = @$row[4];
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
  $custom_params       = @$row[17];
  $subscribeall        = @$row[18];
  $timeout1            = @$row[19];
  $timeout2            = @$row[20];
  $dry                 = @$row[21];
  $oauth_access_token  = @$row[22];
  $oauth_token_expires = @$row[23];
  $oauth_flow          = @$row[24];
  $source_id           = @$row[25];

  if ($enc1 eq "TLS") { $enc1 = "--tls1"; } elsif ($enc1 eq "SSL") { $enc1 = "--ssl1"; } else { undef $enc1; }

  my $template = $run_dir . '/imapsync.XXXXXXX';
  my $passfile1 = File::Temp->new(TEMPLATE => $template);
  my $passfile2 = File::Temp->new(TEMPLATE => $template);
  my $tokenfile1;

  binmode( $passfile1, ":utf8" );

  # Auth-mode-specific argument set
  my @auth_args;
  if (defined($auth_type) && $auth_type eq 'XOAUTH2') {
    # authorization_code sources use a per-user token (keyed by source + user1),
    # not the shared source-level token used by client_credentials.
    if (defined($oauth_flow) && $oauth_flow eq 'authorization_code') {
      my $tsth = $dbh->prepare("SELECT access_token, token_expires FROM imapsync_source_oauth_token WHERE source_id=? AND username=?");
      $tsth->execute($source_id, $user1);
      my $trow = $tsth->fetchrow_arrayref();
      $oauth_access_token  = $trow ? @$trow[0] : undef;
      $oauth_token_expires = $trow ? @$trow[1] : undef;
    }
    # Skip sync if cached token is missing or about to expire (<2 min)
    if (!defined($oauth_access_token) || $oauth_access_token eq ''
        || !defined($oauth_token_expires) || $oauth_token_expires - time() < 120) {
      my $upd = $dbh->prepare("UPDATE imapsync SET returned_text=?, success=0, exit_status='OAUTH_TOKEN_NOT_READY', last_run=NOW(), is_running=0 WHERE id=?");
      $upd->execute("OAuth access token for the configured source is missing or expires too soon; refresh cron will retry.", $id);
      next;
    }
    $tokenfile1 = File::Temp->new(TEMPLATE => $template);
    binmode($tokenfile1, ":utf8");
    print $tokenfile1 "$oauth_access_token\n";
    @auth_args = ("--authmech1", "XOAUTH2",
                  "--oauthaccesstoken1", $tokenfile1->filename);
  } else {
    print $passfile1 "$password1\n";
    @auth_args = ("--passfile1", $passfile1->filename);
    # imapsync auto-picks PLAIN; only emit --authmech1 for LOGIN/CRAM-MD5
    if (defined($auth_type) && $auth_type ne 'PLAIN') {
      push @auth_args, "--authmech1", $auth_type;
    }
  }

  print $passfile2 trim($master_pass) . "\n";

  my @custom_params_a = qqw($custom_params);
  my $custom_params_ref = \@custom_params_a;

  my $generated_cmds = [ "/usr/local/bin/imapsync",
  "--tmpdir", "/tmp",
  "--nofoldersizes",
  "--addheader",
  ($timeout1 le "0" ? () : ('--timeout1', $timeout1)),
  ($timeout2 le "0" ? () : ('--timeout2', $timeout2)),
  ($exclude eq "" ? () : ("--exclude", $exclude)),
  ($subfolder2 eq "" ? () : ('--subfolder2', $subfolder2)),
  ($maxage eq "0" ? () : ('--maxage', $maxage)),
  ($maxbytespersecond eq "0" ? () : ('--maxbytespersecond', $maxbytespersecond)),
  ($delete2duplicates ne "1" ? () : ('--delete2duplicates')),
  ($subscribeall  ne "1" ? () : ('--subscribeall')),
  ($delete1 ne "1" ? () : ('--delete')),
  ($delete2 ne "1" ? () : ('--delete2')),
  ($automap ne "1" ? () : ('--automap')),
  ($skipcrossduplicates ne "1" ? () : ('--skipcrossduplicates')),
  (!defined($enc1) ? () : ($enc1)),
  "--host1", $host1,
  "--user1", $user1,
  @auth_args,
  "--port1", $port1,
  "--host2", "localhost",
  "--user2", $user2 . '*' . trim($master_user),
  "--passfile2", $passfile2->filename,
  ($dry eq "1" ? ('--dry') : ()),
  '--no-modulesversion',
  '--noreleasecheck'];

  try {
    $is_running = $dbh->prepare("UPDATE imapsync SET is_running = 1, success = NULL, exit_status = NULL WHERE id = ?");
    $is_running->bind_param( 1, ${id} );
    $is_running->execute();

    run [@$generated_cmds, @$custom_params_ref], '&>', \my $stdout;

    # check exit code and status
    ($exit_code, $exit_status) = ($stdout =~ m/Exiting\swith\sreturn\svalue\s(\d+)\s\(([^:)]+)/);

    $success = 0;
    if (defined $exit_code && $exit_code == 0) {
      $success = 1;
    }

    $update = $dbh->prepare("UPDATE imapsync SET returned_text = ?, success = ?, exit_status = ? WHERE id = ?");
    $update->bind_param( 1, ${stdout} );
    $update->bind_param( 2, ${success} );
    $update->bind_param( 3, ${exit_status} );
    $update->bind_param( 4, ${id} );
    $update->execute();
  } catch {
    $update = $dbh->prepare("UPDATE imapsync SET returned_text = 'Could not start or finish imapsync', success = 0 WHERE id = ?");
    $update->bind_param( 1, ${id} );
    $update->execute();
  } finally {
    $update = $dbh->prepare("UPDATE imapsync SET last_run = NOW(), is_running = 0 WHERE id = ?");
    $update->bind_param( 1, ${id} );
    $update->execute();
  };


}

$sth->finish();
$dbh->disconnect();

$lockmgr->unlock($lock_file);
