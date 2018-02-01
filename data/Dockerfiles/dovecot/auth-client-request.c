/* Copyright (c) 2003-2017 Dovecot authors, see the included COPYING file */

#include "lib.h"
#include "str.h"
#include "strescape.h"
#include "ostream.h"
#include "auth-client-private.h"
#include "auth-server-connection.h"
#include "auth-client-request.h"


struct auth_client_request {
  pool_t pool;

  struct auth_server_connection *conn;
  unsigned int id;
  time_t created;

  struct auth_request_info request_info;

  auth_request_callback_t *callback;
  void *context;
};

static void auth_server_send_new_request(struct auth_server_connection *conn,
           struct auth_client_request *request)
{
  struct auth_request_info *info = &request->request_info;
  string_t *str;

  str = t_str_new(512);
  str_printfa(str, "AUTH\t%u\t", request->id);
  str_append_tabescaped(str, info->mech);
  str_append(str, "\tservice=");
  str_append_tabescaped(str, info->service);

  if ((info->flags & AUTH_REQUEST_FLAG_SUPPORT_FINAL_RESP) != 0)
    str_append(str, "\tfinal-resp-ok");
  if ((info->flags & AUTH_REQUEST_FLAG_SECURED) != 0)
    str_append(str, "\tsecured");
  if ((info->flags & AUTH_REQUEST_FLAG_NO_PENALTY) != 0)
    str_append(str, "\tno-penalty");
  if ((info->flags & AUTH_REQUEST_FLAG_VALID_CLIENT_CERT) != 0)
    str_append(str, "\tvalid-client-cert");
  if ((info->flags & AUTH_REQUEST_FLAG_DEBUG) != 0)
    str_append(str, "\tdebug");

  if (info->session_id != NULL) {
    str_append(str, "\tsession=");
    str_append_tabescaped(str, info->session_id);
  }
  if (info->cert_username != NULL) {
    str_append(str, "\tcert_username=");
    str_append_tabescaped(str, info->cert_username);
  }
  if (info->local_ip.family != 0)
    str_printfa(str, "\tlip=%s", net_ip2addr(&info->local_ip));
  if (info->remote_ip.family != 0)
    str_printfa(str, "\trip=%s", net_ip2addr(&info->remote_ip));
  if (info->local_port != 0)
    str_printfa(str, "\tlport=%u", info->local_port);
  if (info->remote_port != 0)
    str_printfa(str, "\trport=%u", info->remote_port);

  /* send the real_* variants only when they differ from the unreal
     ones */
  if (info->real_local_ip.family != 0 &&
      !net_ip_compare(&info->real_local_ip, &info->local_ip)) {
    str_printfa(str, "\treal_lip=%s",
          net_ip2addr(&info->real_local_ip));
  }
  if (info->real_remote_ip.family != 0 &&
      !net_ip_compare(&info->real_remote_ip, &info->remote_ip)) {
    str_printfa(str, "\treal_rip=%s",
          net_ip2addr(&info->real_remote_ip));
  }
  if (info->real_local_port != 0 &&
      info->real_local_port != info->local_port)
    str_printfa(str, "\treal_lport=%u", info->real_local_port);
  if (info->real_remote_port != 0 &&
      info->real_remote_port != info->remote_port)
    str_printfa(str, "\treal_rport=%u", info->real_remote_port);
  if (info->local_name != NULL &&
      *info->local_name != '\0') {
    str_append(str, "\tlocal_name=");
    str_append_tabescaped(str, info->local_name);
  }
  if (info->client_id != NULL &&
      *info->client_id != '\0') {
    str_append(str, "\tclient_id=");
    str_append_tabescaped(str, info->client_id);
  }
  if (info->forward_fields != NULL &&
      *info->forward_fields != '\0') {
    str_append(str, "\tforward_fields=");
    str_append_tabescaped(str, info->forward_fields);
  }
  if (info->initial_resp_base64 != NULL) {
    str_append(str, "\tresp=");
    str_append_tabescaped(str, info->initial_resp_base64);
  }
  str_append_c(str, '\n');

  if (o_stream_send(conn->output, str_data(str), str_len(str)) < 0)
    i_error("Error sending request to auth server: %m");
}

struct auth_client_request *
auth_client_request_new(struct auth_client *client,
      const struct auth_request_info *request_info,
      auth_request_callback_t *callback, void *context)
{
  struct auth_client_request *request;
  pool_t pool;

  pool = pool_alloconly_create("auth client request", 512);
  request = p_new(pool, struct auth_client_request, 1);
  request->pool = pool;
  request->conn = client->conn;

  request->request_info = *request_info;
  request->request_info.mech = p_strdup(pool, request_info->mech);
  request->request_info.service = p_strdup(pool, request_info->service);
  request->request_info.session_id =
    p_strdup_empty(pool, request_info->session_id);
  request->request_info.cert_username =
    p_strdup_empty(pool, request_info->cert_username);
  request->request_info.initial_resp_base64 =
    p_strdup_empty(pool, request_info->initial_resp_base64);
  
  request->callback = callback;
  request->context = context;

  request->id =
    auth_server_connection_add_request(request->conn, request);
  request->created = ioloop_time;
  T_BEGIN {
    auth_server_send_new_request(request->conn, request);
  } T_END;
  return request;
}

void auth_client_request_continue(struct auth_client_request *request,
                                  const char *data_base64)
{
  struct const_iovec iov[3];
  const char *prefix;

  prefix = t_strdup_printf("CONT\t%u\t", request->id);

  iov[0].iov_base = prefix;
  iov[0].iov_len = strlen(prefix);
  iov[1].iov_base = data_base64;
  iov[1].iov_len = strlen(data_base64);
  iov[2].iov_base = "\n";
  iov[2].iov_len = 1;

  if (o_stream_sendv(request->conn->output, iov, 3) < 0)
    i_error("Error sending continue request to auth server: %m");
}

static void ATTR_NULL(3, 4)
call_callback(struct auth_client_request *request,
        enum auth_request_status status,
        const char *data_base64,
        const char *const *args)
{
  auth_request_callback_t *callback = request->callback;

  if (status != AUTH_REQUEST_STATUS_CONTINUE)
    request->callback = NULL;
  callback(request, status, data_base64, args, request->context);
}

void auth_client_request_abort(struct auth_client_request **_request)
{
  struct auth_client_request *request = *_request;

  *_request = NULL;

  auth_client_send_cancel(request->conn->client, request->id);
  call_callback(request, AUTH_REQUEST_STATUS_ABORT, NULL, NULL);
  auth_server_connection_remove_request(request->conn, request->id);
  pool_unref(&request->pool);
}

unsigned int auth_client_request_get_id(struct auth_client_request *request)
{
  return request->id;
}

unsigned int
auth_client_request_get_server_pid(struct auth_client_request *request)
{
  return request->conn->server_pid;
}

const char *auth_client_request_get_cookie(struct auth_client_request *request)
{
  return request->conn->cookie;
}

bool auth_client_request_is_aborted(struct auth_client_request *request)
{
  return request->callback == NULL;
}

time_t auth_client_request_get_create_time(struct auth_client_request *request)
{
  return request->created;
}

void auth_client_request_server_input(struct auth_client_request *request,
              enum auth_request_status status,
              const char *const *args)
{
  const char *const *tmp, *base64_data = NULL;

  if (request->callback == NULL) {
    /* aborted already */
    return;
  }

  switch (status) {
  case AUTH_REQUEST_STATUS_OK:
    for (tmp = args; *tmp != NULL; tmp++) {
      if (strncmp(*tmp, "resp=", 5) == 0) {
        base64_data = *tmp + 5;
        break;
      }
    }
    break;
  case AUTH_REQUEST_STATUS_CONTINUE:
    base64_data = args[0];
    args = NULL;
    break;
  case AUTH_REQUEST_STATUS_FAIL:
  case AUTH_REQUEST_STATUS_INTERNAL_FAIL:
  case AUTH_REQUEST_STATUS_ABORT:
    break;
  }

  call_callback(request, status, base64_data, args);
  if (status != AUTH_REQUEST_STATUS_CONTINUE)
    pool_unref(&request->pool);
}

void auth_client_send_cancel(struct auth_client *client, unsigned int id)
{
  const char *str = t_strdup_printf("CANCEL\t%u\n", id);

  if (o_stream_send_str(client->conn->output, str) < 0)
    i_error("Error sending request to auth server: %m");
}
