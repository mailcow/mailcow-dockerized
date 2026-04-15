#!/usr/bin/perl

use DBI;
use LockFile::Simple qw(lock trylock unlock);
use Data::Dumper qw(Dumper);
use IPC::Run 'run';
use File::Temp;
use Try::Tiny;
use Parallel::ForkManager;
use Redis;
use sigtrap 'handler' => \&sig_handler, qw(INT TERM KILL QUIT);

sub trim { my $s = shift; $s =~ s/^\s+|\s+$//g; return $s };

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

our $pm;

sub sig_handler {
  if (defined $pm) {
    foreach my $child_pid (keys %{ $pm->{processes} }) {
      kill 'TERM', $child_pid;
    }
  }
  die "sig_handler received signal, preparing to exit...\n";
};

sub run_one_job {
  my ($dbh, $row_ref, $master_user, $master_pass) = @_;

  my $id                  = $row_ref->[0];
  my $user1               = $row_ref->[1];
  my $user2               = $row_ref->[2];
  my $host1               = $row_ref->[3];
  my $authmech1           = $row_ref->[4];
  my $password1           = $row_ref->[5];
  my $exclude             = $row_ref->[6];
  my $port1               = $row_ref->[7];
  my $enc1                = $row_ref->[8];
  my $delete2duplicates   = $row_ref->[9];
  my $maxage              = $row_ref->[10];
  my $subfolder2          = $row_ref->[11];
  my $delete1             = $row_ref->[12];
  my $delete2             = $row_ref->[13];
  my $automap             = $row_ref->[14];
  my $skipcrossduplicates = $row_ref->[15];
  my $maxbytespersecond   = $row_ref->[16];
  my $custom_params       = $row_ref->[17];
  my $subscribeall        = $row_ref->[18];
  my $timeout1            = $row_ref->[19];
  my $timeout2            = $row_ref->[20];
  my $dry                 = $row_ref->[21];

  if ($enc1 eq "TLS") { $enc1 = "--tls1"; } elsif ($enc1 eq "SSL") { $enc1 = "--ssl1"; } else { undef $enc1; }

  my $template = "/tmp/imapsync.XXXXXXX";
  my $passfile1 = File::Temp->new(TEMPLATE => $template);
  my $passfile2 = File::Temp->new(TEMPLATE => $template);

  binmode( $passfile1, ":utf8" );

  print $passfile1 "$password1\n";
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
  "--passfile1", $passfile1->filename,
  "--port1", $port1,
  "--host2", "localhost",
  "--user2", $user2 . '*' . trim($master_user),
  "--passfile2", $passfile2->filename,
  ($dry eq "1" ? ('--dry') : ()),
  '--no-modulesversion',
  '--noreleasecheck'];

  try {
    my $is_running = $dbh->prepare("UPDATE imapsync SET is_running = 1, success = NULL, exit_status = NULL WHERE id = ?");
    $is_running->bind_param( 1, $id );
    $is_running->execute();

    run [@$generated_cmds, @$custom_params_ref], '&>', \my $stdout;

    my ($exit_code, $exit_status) = ($stdout =~ m/Exiting\swith\sreturn\svalue\s(\d+)\s\(([^:)]+)/);

    my $success = 0;
    if (defined $exit_code && $exit_code == 0) {
      $success = 1;
    }

    my $update = $dbh->prepare("UPDATE imapsync SET returned_text = ?, success = ?, exit_status = ? WHERE id = ?");
    $update->bind_param( 1, $stdout );
    $update->bind_param( 2, $success );
    $update->bind_param( 3, $exit_status );
    $update->bind_param( 4, $id );
    $update->execute();
  } catch {
    my $update = $dbh->prepare("UPDATE imapsync SET returned_text = 'Could not start or finish imapsync', success = 0 WHERE id = ?");
    $update->bind_param( 1, $id );
    $update->execute();
  } finally {
    my $update = $dbh->prepare("UPDATE imapsync SET last_run = NOW(), is_running = 0 WHERE id = ?");
    $update->bind_param( 1, $id );
    $update->execute();
  };
}

my $run_dir = "/tmp";
my $dsn = 'DBI:mysql:database=' . $ENV{'DBNAME'} . ';mysql_socket=/var/run/mysqld/mysqld.sock';
my $lock_file = $run_dir . "/imapsync_busy";
my $lockmgr = LockFile::Simple->make(-autoclean => 1, -max => 1);
$lockmgr->lock($lock_file) || die "can't lock ${lock_file}";

my $max_parallel = 1;
try {
  my $redis = Redis->new(
    server    => 'redis-mailcow:6379',
    password  => $ENV{'REDISPASS'},
    reconnect => 10,
    every     => 1_000_000,
  );
  my $val = $redis->get('SYNCJOBS_MAX_PARALLEL');
  if (defined $val && $val =~ /^\d+$/) {
    $max_parallel = int($val);
  }
  $redis->quit();
} catch {
  warn "Could not read SYNCJOBS_MAX_PARALLEL from Redis, defaulting to 1: $_";
};
$max_parallel = 1  if $max_parallel < 1;
$max_parallel = 50 if $max_parallel > 50;

my $dbh = DBI->connect($dsn, $ENV{'DBUSER'}, $ENV{'DBPASS'}, {
  mysql_auto_reconnect => 1,
  mysql_enable_utf8mb4 => 1
});
$dbh->do("UPDATE imapsync SET is_running = 0");

open my $file, '<', "/etc/sogo/sieve.creds";
my $creds = <$file>;
close $file;
my ($master_user, $master_pass) = split /:/, $creds;

my $sth = $dbh->prepare("SELECT id,
  user1,
  user2,
  host1,
  authmech1,
  password1,
  exclude,
  port1,
  enc1,
  delete2duplicates,
  maxage,
  subfolder2,
  delete1,
  delete2,
  automap,
  skipcrossduplicates,
  maxbytespersecond,
  custom_params,
  subscribeall,
  timeout1,
  timeout2,
  dry
    FROM imapsync
      WHERE active = 1
        AND is_running = 0
        AND (
          UNIX_TIMESTAMP(NOW()) - UNIX_TIMESTAMP(last_run) > mins_interval * 60
          OR
          last_run IS NULL)
  ORDER BY last_run");

$sth->execute();
my @jobs;
while (my $row = $sth->fetchrow_arrayref()) {
  push @jobs, [ @$row ];
}
$sth->finish();
$dbh->disconnect();

$pm = Parallel::ForkManager->new($max_parallel);

JOB:
foreach my $job (@jobs) {
  my $pid = $pm->start;
  if ($pid) {
    next JOB;
  }

  my $child_dbh = DBI->connect($dsn, $ENV{'DBUSER'}, $ENV{'DBPASS'}, {
    mysql_auto_reconnect => 1,
    mysql_enable_utf8mb4 => 1
  });
  run_one_job($child_dbh, $job, $master_user, $master_pass);
  $child_dbh->disconnect();

  $pm->finish;
}

$pm->wait_all_children;

$lockmgr->unlock($lock_file);
