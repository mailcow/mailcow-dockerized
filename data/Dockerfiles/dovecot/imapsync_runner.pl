#!/usr/bin/perl

use DBI;
use LockFile::Simple qw(lock trylock unlock);
use Proc::ProcessTable;
use Data::Dumper qw(Dumper);
use IPC::Run 'run';
use JSON::PP;
use File::Temp;
use Try::Tiny;
use POSIX ();
use sigtrap 'handler' => \&sig_handler, qw(INT TERM KILL QUIT);

sub trim { my $s = shift; $s =~ s/^\s+|\s+$//g; return $s };

# Move a finished job to the back of the queue, keeping order_id contiguous (1..N).
# Called only from the parent after reaping a child, so all rotations are serialized.
sub rotate_to_back {
  my ($dbh, $jid) = @_;
  return unless defined $jid;
  my ($cnt) = $dbh->selectrow_array("SELECT COUNT(*) FROM imapsync");
  my ($old) = $dbh->selectrow_array("SELECT order_id FROM imapsync WHERE id = ?", undef, $jid);
  return unless (defined $cnt && defined $old && $old < $cnt);
  # close the gap left behind, then place this job last
  $dbh->do("UPDATE imapsync SET order_id = order_id - 1 WHERE order_id > ? ORDER BY order_id ASC", undef, $old);
  $dbh->do("UPDATE imapsync SET order_id = ? WHERE id = ?", undef, $cnt, $jid);
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

# Max concurrent imapsync processes (admin-configurable, stored in MySQL). Fallback 1.
my $max_parallel = 1;
my ($mp) = $dbh->selectrow_array("SELECT value FROM imapsync_settings WHERE name = 'max_parallel'");
$max_parallel = int($mp) if defined($mp) && int($mp) >= 1;

# Global per-process bandwidth cap in bytes/s (0 = off). Applied to every imapsync run below.
my $global_bw = 0;
my ($gbw) = $dbh->selectrow_array("SELECT value FROM imapsync_settings WHERE name = 'max_bytes_per_second'");
$global_bw = int($gbw) if defined($gbw) && int($gbw) > 0;

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
  ORDER BY i.order_id ASC, i.id ASC");

$sth->execute();
my @rows = @{$sth->fetchall_arrayref()};
$sth->finish();

# Fork pool: run up to $max_parallel imapsync children at once, in order_id order.
my $active = 0;
my %pid_job;   # child pid => syncjob id, so the parent can rotate it to the back when it finishes
foreach my $row (@rows) {
  # Throttle: once max children are running, reap one before starting the next
  if ($active >= $max_parallel) {
    my $done = wait();
    my $rc = $? >> 8;
    $active-- if $active > 0;
    if (defined $pid_job{$done}) {
      rotate_to_back($dbh, $pid_job{$done}) if $rc == 0;   # rotate only jobs that actually ran
      delete $pid_job{$done};
    }
  }
  my $pid = fork();
  next if (!defined $pid);
  if ($pid != 0) { $pid_job{$pid} = @$row[0]; $active++; next; }   # parent: remember pid->job, continue

  # ---- CHILD ---- own DB connection so the parent's handle stays intact across forks
  $dbh->{InactiveDestroy} = 1;
  $dbh = DBI->connect($dsn, $ENV{'DBUSER'}, $ENV{'DBPASS'}, {
    mysql_auto_reconnect => 1,
    mysql_enable_utf8mb4 => 1
  });

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
      $dbh->disconnect();
      unlink $passfile1->filename if defined $passfile1;
      unlink $passfile2->filename if defined $passfile2;
      # exit 75 (EX_TEMPFAIL): did not actually sync -> parent keeps its queue position (no rotate)
      POSIX::_exit(75);
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

  # Structured custom params: JSON array of {o,v}. Build one argv token per pair as "--opt=value"
  # (value glued to its option) so a value can never become a separate option; passed via
  # IPC::Run list form (execvp, no shell). Only allowlisted option names are stored (validated on write).
  my @custom_params_a;
  my $cp_pairs = eval { decode_json(defined($custom_params) && $custom_params ne '' ? $custom_params : '[]') };
  if (ref($cp_pairs) eq 'ARRAY') {
    for my $p (@$cp_pairs) {
      next unless ref($p) eq 'HASH' && defined $p->{o} && $p->{o} ne '';
      (my $ov = defined($p->{v}) ? $p->{v} : '') =~ s/\x00//g;
      push @custom_params_a, ($ov eq '' ? "--$p->{o}" : "--$p->{o}=$ov");
    }
  }
  my $custom_params_ref = \@custom_params_a;

  # Effective per-process bandwidth cap (bytes/s): per-job value, capped by the global limit.
  my $eff_bw = int($maxbytespersecond);
  if ($global_bw > 0) {
    $eff_bw = ($eff_bw > 0 && $eff_bw < $global_bw) ? $eff_bw : $global_bw;
  }

  my $generated_cmds = [ "/usr/local/bin/imapsync",
  "--tmpdir", "/tmp",
  "--nofoldersizes",
  "--addheader",
  ($timeout1 le "0" ? () : ('--timeout1', $timeout1)),
  ($timeout2 le "0" ? () : ('--timeout2', $timeout2)),
  ($exclude eq "" ? () : ("--exclude", $exclude)),
  ($subfolder2 eq "" ? () : ('--subfolder2', $subfolder2)),
  ($maxage eq "0" ? () : ('--maxage', $maxage)),
  ($eff_bw <= 0 ? () : ('--maxbytespersecond', $eff_bw)),
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

  $dbh->disconnect();
  # Remove this child's own temp files, then _exit so END/DESTROY are skipped — this keeps
  # the parent's LockFile lock and DB handle intact (a plain exit() would run autoclean and
  # release the shared lock while the parent + siblings are still working).
  unlink $passfile1->filename if defined $passfile1;
  unlink $passfile2->filename if defined $passfile2;
  unlink $tokenfile1->filename if defined $tokenfile1;
  POSIX::_exit(0);   # ---- end CHILD ----
}

# Parent: wait for all remaining children, rotating each finished job to the back
while ((my $done = wait()) != -1) {
  my $rc = $? >> 8;
  if (defined $pid_job{$done}) {
    rotate_to_back($dbh, $pid_job{$done}) if $rc == 0;   # rotate only jobs that actually ran
    delete $pid_job{$done};
  }
}

$dbh->disconnect();

$lockmgr->unlock($lock_file);
