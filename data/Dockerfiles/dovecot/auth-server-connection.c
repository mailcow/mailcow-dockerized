/* Copyright (c) 2003-2017 Dovecot authors, see the included COPYING file */

#include "lib.h"
#include "array.h"
#include "hash.h"
#include "hostpid.h"
#include "ioloop.h"
#include "istream.h"
#include "ostream.h"
#include "net.h"
#include "strescape.h"
#include "eacces-error.h"
#include "auth-client-private.h"
#include "auth-client-request.h"
#include "auth-server-connection.h"

#include <unistd.h>

#define AUTH_SERVER_CONN_MAX_LINE_LENGTH AUTH_CLIENT_MAX_LINE_LENGTH
#define AUTH_HANDSHAKE_TIMEOUT (30*1000)
#define AUTH_SERVER_RECONNECT_TIMEOUT_SECS 5

static void
auth_server_connection_reconnect(struct auth_server_connection *conn,
         const char *disconnect_reason);

static int
auth_server_input_mech(struct auth_server_connection *conn,
           const char *const *args)
{
  struct auth_mech_desc mech_desc;

  if (conn->handshake_received) {
    i_error("BUG: Authentication server already sent handshake");
    return -1;
  }
  if (args[0] == NULL) {
    i_error("BUG: Authentication server sent broken MECH line");
    return -1;
  }

  i_zero(&mech_desc);
  mech_desc.name = p_strdup(conn->pool, args[0]);

  if (strcmp(mech_desc.name, "PLAIN") == 0)
    conn->has_plain_mech = TRUE;

  for (args++; *args != NULL; args++) {
    if (strcmp(*args, "private") == 0)
      mech_desc.flags |= MECH_SEC_PRIVATE;
    else if (strcmp(*args, "anonymous") == 0)
      mech_desc.flags |= MECH_SEC_ANONYMOUS;
    else if (strcmp(*args, "plaintext") == 0)
      mech_desc.flags |= MECH_SEC_PLAINTEXT;
    else if (strcmp(*args, "dictionary") == 0)
      mech_desc.flags |= MECH_SEC_DICTIONARY;
    else if (strcmp(*args, "active") == 0)
      mech_desc.flags |= MECH_SEC_ACTIVE;
    else if (strcmp(*args, "forward-secrecy") == 0)
      mech_desc.flags |= MECH_SEC_FORWARD_SECRECY;
    else if (strcmp(*args, "mutual-auth") == 0)
      mech_desc.flags |= MECH_SEC_MUTUAL_AUTH;
  }
  array_append(&conn->available_auth_mechs, &mech_desc, 1);
  return 0;
}

static int
auth_server_input_spid(struct auth_server_connection *conn,
           const char *const *args)
{
  if (conn->handshake_received) {
    i_error("BUG: Authentication server already sent handshake");
    return -1;
  }

  if (str_to_uint(args[0], &conn->server_pid) < 0) {
    i_error("BUG: Authentication server sent invalid PID");
    return -1;
  }
  return 0;
}

static int
auth_server_input_cuid(struct auth_server_connection *conn,
           const char *const *args)
{
  if (conn->handshake_received) {
    i_error("BUG: Authentication server already sent handshake");
    return -1;
  }
  if (args[0] == NULL ||
      str_to_uint(args[0], &conn->connect_uid) < 0) {
    i_error("BUG: Authentication server sent broken CUID line");
    return -1;
  }
  return 0;
}

static int
auth_server_input_cookie(struct auth_server_connection *conn,
       const char *const *args)
{
  if (conn->cookie != NULL) {
    i_error("BUG: Authentication server already sent cookie");
    return -1;
  }
  conn->cookie = p_strdup(conn->pool, args[0]);
  return 0;
}

static int auth_server_input_done(struct auth_server_connection *conn)
{
  if (array_count(&conn->available_auth_mechs) == 0) {
    i_error("BUG: Authentication server returned no mechanisms");
    return -1;
  }
  if (conn->cookie == NULL) {
    i_error("BUG: Authentication server didn't send a cookie");
    return -1;
  }

  if (conn->to != NULL)
    timeout_remove(&conn->to);

  conn->handshake_received = TRUE;
  if (conn->client->connect_notify_callback != NULL) {
    conn->client->connect_notify_callback(conn->client, TRUE,
        conn->client->connect_notify_context);
  }
  return 0;
}

static int
auth_server_lookup_request(struct auth_server_connection *conn,
         const char *id_arg, bool remove,
         struct auth_client_request **request_r)
{
  struct auth_client_request *request;
  unsigned int id;

  if (id_arg == NULL || str_to_uint(id_arg, &id) < 0) {
    i_error("BUG: Authentication server input missing ID");
    return -1;
  }

  request = hash_table_lookup(conn->requests, POINTER_CAST(id));
  if (request == NULL) {
    i_error("BUG: Authentication server sent unknown id %u", id);
    return -1;
  }
  if (remove || auth_client_request_is_aborted(request))
    hash_table_remove(conn->requests, POINTER_CAST(id));

  *request_r = request;
  return 0;
}

static int
auth_server_input_ok(struct auth_server_connection *conn,
         const char *const *args)
{
  struct auth_client_request *request;

  if (auth_server_lookup_request(conn, args[0], TRUE, &request) < 0)
    return -1;
  auth_client_request_server_input(request, AUTH_REQUEST_STATUS_OK,
           args + 1);
  return 0;
}

static int auth_server_input_cont(struct auth_server_connection *conn,
          const char *const *args)
{
  struct auth_client_request *request;

  if (str_array_length(args) < 2) {
    i_error("BUG: Authentication server sent broken CONT line");
    return -1;
  }

  if (auth_server_lookup_request(conn, args[0], FALSE, &request) < 0)
    return -1;
  auth_client_request_server_input(request, AUTH_REQUEST_STATUS_CONTINUE,
           args + 1);
  return 0;
}

static int auth_server_input_fail(struct auth_server_connection *conn,
          const char *const *args)
{
  struct auth_client_request *request;

  if (auth_server_lookup_request(conn, args[0], TRUE, &request) < 0)
    return -1;
  auth_client_request_server_input(request, AUTH_REQUEST_STATUS_FAIL,
           args + 1);
  return 0;
}

static int
auth_server_connection_input_line(struct auth_server_connection *conn,
          const char *line)
{
  const char *const *args;

  if (conn->client->debug)
    i_debug("auth input: %s", line);

  args = t_strsplit_tabescaped(line);
  if (args[0] == NULL) {
    i_error("Auth server sent empty line");
    return -1;
  }
  if (strcmp(args[0], "OK") == 0)
    return auth_server_input_ok(conn, args + 1);
  else if (strcmp(args[0], "CONT") == 0)
    return auth_server_input_cont(conn, args + 1);
  else if (strcmp(args[0], "FAIL") == 0)
    return auth_server_input_fail(conn, args + 1);
  else if (strcmp(args[0], "MECH") == 0)
    return auth_server_input_mech(conn, args + 1);
  else if (strcmp(args[0], "SPID") == 0)
    return auth_server_input_spid(conn, args + 1);
  else if (strcmp(args[0], "CUID") == 0)
    return auth_server_input_cuid(conn, args + 1);
  else if (strcmp(args[0], "COOKIE") == 0)
    return auth_server_input_cookie(conn, args + 1);
  else if (strcmp(args[0], "DONE") == 0)
    return auth_server_input_done(conn);
  else {
    i_error("Auth server sent unknown command: %s", args[0]);
    return -1;
  }
}

static void auth_server_connection_input(struct auth_server_connection *conn)
{
  struct istream *input;
  const char *line, *error;
  int ret;

  switch (i_stream_read(conn->input)) {
  case 0:
    return;
  case -1:
    /* disconnected */
    error = conn->input->stream_errno != 0 ?
      strerror(conn->input->stream_errno) : "EOF";
    auth_server_connection_reconnect(conn, error);
    return;
  case -2:
    /* buffer full - can't happen unless auth is buggy */
    i_error("BUG: Auth server sent us more than %d bytes of data",
      AUTH_SERVER_CONN_MAX_LINE_LENGTH);
    auth_server_connection_disconnect(conn, "buffer full");
    return;
  }

  if (!conn->version_received) {
    line = i_stream_next_line(conn->input);
    if (line == NULL)
      return;

    /* make sure the major version matches */
    if (strncmp(line, "VERSION\t", 8) != 0 ||
        !str_uint_equals(t_strcut(line + 8, '\t'),
             AUTH_CLIENT_PROTOCOL_MAJOR_VERSION)) {
      i_error("Authentication server not compatible with "
        "this client (mixed old and new binaries?)");
      auth_server_connection_disconnect(conn,
        "incompatible server");
      return;
    }
    conn->version_received = TRUE;
  }

  input = conn->input;
  i_stream_ref(input);
  while ((line = i_stream_next_line(input)) != NULL && !input->closed) {
    T_BEGIN {
      ret = auth_server_connection_input_line(conn, line);
    } T_END;

    if (ret < 0) {
      auth_server_connection_disconnect(conn, t_strdup_printf(
        "Received broken input: %s", line));
      break;
    }
  }
  i_stream_unref(&input);
}

struct auth_server_connection *
auth_server_connection_init(struct auth_client *client)
{
  struct auth_server_connection *conn;
  pool_t pool;

  pool = pool_alloconly_create("auth server connection", 1024);
  conn = p_new(pool, struct auth_server_connection, 1);
  conn->pool = pool;

  conn->client = client;
  conn->fd = -1;
  hash_table_create_direct(&conn->requests, pool, 100);
  i_array_init(&conn->available_auth_mechs, 8);
  return conn;
}

static void
auth_server_connection_remove_requests(struct auth_server_connection *conn,
               const char *disconnect_reason)
{
  static const char *const temp_failure_args[] = { "temp", NULL };
  struct hash_iterate_context *iter;
  void *key;
  struct auth_client_request *request;
  time_t created, oldest = 0;
  unsigned int request_count = 0;

  if (hash_table_count(conn->requests) == 0)
    return;

  iter = hash_table_iterate_init(conn->requests);
  while (hash_table_iterate(iter, conn->requests, &key, &request)) {
    if (!auth_client_request_is_aborted(request)) {
      request_count++;
      created = auth_client_request_get_create_time(request);
      if (oldest > created || oldest == 0)
        oldest = created;
    }

    auth_client_request_server_input(request,
      AUTH_REQUEST_STATUS_INTERNAL_FAIL,
      temp_failure_args);
  }
  hash_table_iterate_deinit(&iter);
  hash_table_clear(conn->requests, FALSE);

  if (request_count > 0) {
    i_warning("Auth connection closed with %u pending requests "
        "(max %u secs, pid=%s, %s)", request_count,
        (unsigned int)(ioloop_time - oldest),
        my_pid, disconnect_reason);
  }
}

void auth_server_connection_disconnect(struct auth_server_connection *conn,
               const char *reason)
{
  conn->handshake_received = FALSE;
  conn->version_received = FALSE;
  conn->has_plain_mech = FALSE;
  conn->server_pid = 0;
  conn->connect_uid = 0;
  conn->cookie = NULL;
  array_clear(&conn->available_auth_mechs);

  if (conn->to != NULL)
    timeout_remove(&conn->to);
  if (conn->io != NULL)
    io_remove(&conn->io);
  if (conn->fd != -1) {
    i_stream_destroy(&conn->input);
    o_stream_destroy(&conn->output);

    if (close(conn->fd) < 0)
      i_error("close(auth server connection) failed: %m");
    conn->fd = -1;
  }

  auth_server_connection_remove_requests(conn, reason);

  if (conn->client->connect_notify_callback != NULL) {
    conn->client->connect_notify_callback(conn->client, FALSE,
        conn->client->connect_notify_context);
  }
}

static void auth_server_reconnect_timeout(struct auth_server_connection *conn)
{
  (void)auth_server_connection_connect(conn);
}

static void
auth_server_connection_reconnect(struct auth_server_connection *conn,
         const char *disconnect_reason)
{
  time_t next_connect;

  auth_server_connection_disconnect(conn, disconnect_reason);

  next_connect = conn->last_connect + AUTH_SERVER_RECONNECT_TIMEOUT_SECS;
  conn->to = timeout_add(ioloop_time >= next_connect ? 0 :
             (next_connect - ioloop_time) * 1000,
             auth_server_reconnect_timeout, conn);
}

void auth_server_connection_deinit(struct auth_server_connection **_conn)
{
        struct auth_server_connection *conn = *_conn;

  *_conn = NULL;

  auth_server_connection_disconnect(conn, "deinitializing");
  i_assert(hash_table_count(conn->requests) == 0);
  hash_table_destroy(&conn->requests);
  array_free(&conn->available_auth_mechs);
  pool_unref(&conn->pool);
}

static void auth_client_handshake_timeout(struct auth_server_connection *conn)
{
  i_error("Timeout waiting for handshake from auth server. "
    "my pid=%u, input bytes=%"PRIuUOFF_T,
    conn->client->client_pid, conn->input->v_offset);
  auth_server_connection_reconnect(conn, "auth server timeout");
}

int auth_server_connection_connect(struct auth_server_connection *conn)
{
  const char *handshake;
  int fd;

  i_assert(conn->fd == -1);

  conn->last_connect = ioloop_time;
  if (conn->to != NULL)
    timeout_remove(&conn->to);

  /* max. 1 second wait here. */
  fd = net_connect_unix_with_retries(conn->client->auth_socket_path,
             1000);
  if (fd == -1) {
    if (errno == EACCES) {
      i_error("auth: %s",
        eacces_error_get("connect",
          conn->client->auth_socket_path));
    } else {
      i_error("auth: connect(%s) failed: %m",
        conn->client->auth_socket_path);
    }
    return -1;
  }
  conn->fd = fd;
  conn->io = io_add(fd, IO_READ, auth_server_connection_input, conn);
  conn->input = i_stream_create_fd(fd, AUTH_SERVER_CONN_MAX_LINE_LENGTH,
           FALSE);
  conn->output = o_stream_create_fd(fd, (size_t)-1, FALSE);

  handshake = t_strdup_printf("VERSION\t%u\t%u\nCPID\t%u\n",
            AUTH_CLIENT_PROTOCOL_MAJOR_VERSION,
                                    AUTH_CLIENT_PROTOCOL_MINOR_VERSION,
            conn->client->client_pid);
  if (o_stream_send_str(conn->output, handshake) < 0) {
    i_warning("Error sending handshake to auth server: %s",
        o_stream_get_error(conn->output));
    auth_server_connection_disconnect(conn,
      o_stream_get_error(conn->output));
    return -1;
  }

  conn->to = timeout_add(AUTH_HANDSHAKE_TIMEOUT,
             auth_client_handshake_timeout, conn);
  return 0;
}

unsigned int
auth_server_connection_add_request(struct auth_server_connection *conn,
           struct auth_client_request *request)
{
  unsigned int id;

  id = ++conn->client->request_id_counter;
  if (id == 0) {
    /* wrapped - ID 0 not allowed */
    id = ++conn->client->request_id_counter;
  }
  i_assert(hash_table_lookup(conn->requests, POINTER_CAST(id)) == NULL);
  hash_table_insert(conn->requests, POINTER_CAST(id), request);
  return id;
}

void auth_server_connection_remove_request(struct auth_server_connection *conn, unsigned int id)
{
  i_assert(conn->handshake_received);
  hash_table_remove(conn->requests, POINTER_CAST(id));
}
