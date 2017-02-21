.. index:: Vacation
.. _settings-managesieve-vacation:

********
Vacation
********

The vacation autoresponder's purpose is to provide correspondents with
notification that the user is away for an extended period of time and that
they should not expect quick responses.

**Vacation** is used to respond to an incoming message with another message.

This interface is part of :ref:`settings-managesieve-filters` functionality
and provides a simple way to manage vacation responses.


Vacation message
----------------

To enable the autoresponder you have to set at least the response body and change
the status to *On*.

**Subject**
  Response subject is optional. By default the reply subject will be set
  to *Auto: <original subject>*

**Body**
  Response body. Here you put the reason of your absence or any other text
  that will be send to sender.

**Vacation start/end**
  These fields define when the vacation rule is active and are optional.

**Status**
  This field activates the rule. If you always use the same response body it is
  convenient to disable the vacation rule when it's not needed and enable again
  another time.

Advanced settings
-----------------

**Reply sender address**
  This is an email address that will be used as sender of the vacation reply.

**My email addresses**
  Normally the vacation response is send if recipient address of the incoming
  message is one of your addresses known to the server. Here you can add
  more addresses.

**Reply interval**
  This parameter defines how often the reply to the same sender is generated.
  When you receive a lot of messages from the same sender in short time,
  usually you don't want to reply to all of them. By default reply is send once a day.

**Incoming message action**
  This field defines an action taken on the incoming message. You can discard or keep
  it or redirect/copy to another account (so it can be handled by another person).
