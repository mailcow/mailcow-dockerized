/**
 * Roundcube splitter GUI class
 *
 * @licstart  The following is the entire license notice for the
 * JavaScript code in this file.
 *
 * Copyright (c) 2006-2014, The Roundcube Dev Team
 *
 * The JavaScript code in this page is free software: you can redistribute it
 * and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation, either version 3 of
 * the License, or (at your option) any later version.
 *
 * @licend  The above is the entire license notice
 * for the JavaScript code in this file.
 *
 * @constructor
 */
function rcube_splitter(attrib)
{
  this.p1id = attrib.p1;
  this.p2id = attrib.p2;
  this.id = attrib.id ? attrib.id : this.p1id + '_' + this.p2id + '_splitter';
  this.orientation = attrib.orientation;
  this.horizontal = (this.orientation == 'horizontal' || this.orientation == 'h');
  this.pos = attrib.start ? attrib.start * 1 : 0;
  this.relative = attrib.relative ? true : false;
  this.drag_active = false;
  this.callback = attrib.callback;

  var me = this;

  this.init = function()
  {
    this.p1 = document.getElementById(this.p1id);
    this.p2 = document.getElementById(this.p2id);

    // create and position the handle for this splitter
    this.p1pos = this.relative ? $(this.p1).position() : $(this.p1).offset();
    this.p2pos = this.relative ? $(this.p2).position() : $(this.p2).offset();

    if (this.horizontal) {
      var top = this.p1pos.top + this.p1.offsetHeight;
      this.layer = new rcube_layer(this.id, {x: 0, y: top, height: 10,
        width: '100%', vis: 1, parent: this.p1.parentNode});
    }
    else {
      var left = this.p1pos.left + this.p1.offsetWidth;
      this.layer = new rcube_layer(this.id, {x: left, y: 0, width: 10,
        height: '100%', vis: 1,  parent: this.p1.parentNode});
    }

    this.elm = this.layer.elm;
    this.elm.className = 'splitter '+(this.horizontal ? 'splitter-h' : 'splitter-v');
    this.elm.unselectable = 'on';

    // add the mouse event listeners
    $(this.elm).mousedown(onDragStart);
    if (bw.ie)
      $(window).resize(onResize);

    // read saved position from cookie
    var cookie = rcmail.get_cookie(this.id);
    if (cookie && !isNaN(cookie)) {
      this.pos = parseFloat(cookie);
      this.resize();
    }
    else if (this.pos) {
      this.resize();
      this.set_cookie();
    }
  };

  /**
   * Set size and position of all DOM objects
   * according to the saved splitter position
   */
  this.resize = function()
  {
    if (this.horizontal) {
      var lh = this.layer.height;
      this.p1.style.height = Math.floor(this.pos - this.p1pos.top - lh / 2) + 'px';
      this.p2.style.top = Math.ceil(this.pos + lh / 2) + 'px';
      this.layer.move(this.layer.x, Math.round(this.pos - lh / 2 + 1));
      if (bw.ie) {
        var new_height = parseInt(this.p2.parentNode.offsetHeight, 10) - parseInt(this.p2.style.top, 10);
        this.p2.style.height = (new_height > 0 ? new_height : 0) + 'px';
      }
    }
    else {
      this.p1.style.width = Math.floor(this.pos - this.p1pos.left - this.layer.width / 2) + 'px';
      this.p2.style.left = Math.ceil(this.pos + this.layer.width / 2) + 'px';
      this.layer.move(Math.round(this.pos - this.layer.width / 2 + 1), this.layer.y);
      if (bw.ie) {
        var new_width = parseInt(this.p2.parentNode.offsetWidth, 10) - parseInt(this.p2.style.left, 10) ;
        this.p2.style.width = (new_width > 0 ? new_width : 0) + 'px';
      }
    }
    $(this.p2).resize();
    $(this.p1).resize();
  };

  /**
   * Handler for mousedown events
   */
  function onDragStart(e)
  {
    me.drag_active = true;

    // disable text selection while dragging the splitter
    if (bw.konq || bw.chrome || bw.safari)
      document.body.style.webkitUserSelect = 'none';

    me.p1pos = me.relative ? $(me.p1).position() : $(me.p1).offset();
    me.p2pos = me.relative ? $(me.p2).position() : $(me.p2).offset();

    // start listening to mousemove events
    $(document).on('mousemove.' + me.id, onDrag).on('mouseup.' + me.id, onDragStop);

    // enable dragging above iframes
    $('iframe').each(function() {
      $('<div class="iframe-splitter-fix"></div>')
        .css({background: '#fff',
          width: this.offsetWidth+'px', height: this.offsetHeight+'px',
          position: 'absolute', opacity: '0.001', zIndex: 1000
        })
        .css($(this).offset())
        .appendTo('body');
      });
  };

  /**
   * Handler for mousemove events
   */
  function onDrag(e)
  {
    if (!me.drag_active)
      return false;

    // with timing events dragging action is more responsive
    window.clearTimeout(me.ts);
    me.ts = window.setTimeout(function() { onDragAction(e); }, 1);

    return false;
  };

  function onDragAction(e)
  {
    var pos = rcube_event.get_mouse_pos(e);

    if (me.relative) {
      var parent = $(me.p1.parentNode).offset();
      pos.x -= parent.left;
      pos.y -= parent.top;
    }

    if (me.horizontal) {
      if (((pos.y - me.layer.height * 1.5) > me.p1pos.top) && ((pos.y + me.layer.height * 1.5) < (me.p2pos.top + me.p2.offsetHeight))) {
        me.pos = pos.y;
        me.resize();
      }
    }
    else if (((pos.x - me.layer.width * 1.5) > me.p1pos.left) && ((pos.x + me.layer.width * 1.5) < (me.p2pos.left + me.p2.offsetWidth))) {
      me.pos = pos.x;
      me.resize();
    }

    me.p1pos = me.relative ? $(me.p1).position() : $(me.p1).offset();
    me.p2pos = me.relative ? $(me.p2).position() : $(me.p2).offset();
  };

  /**
   * Handler for mouseup events
   */
  function onDragStop(e)
  {
    me.drag_active = false;

    // resume the ability to highlight text
    if (bw.konq || bw.chrome || bw.safari)
      document.body.style.webkitUserSelect = 'auto';

    // cancel the listening for drag events
    $(document).off('.' + me.id);

    // remove temp divs
    $('div.iframe-splitter-fix').remove();

    me.set_cookie();

    if (typeof me.callback == 'function')
      me.callback(me);

    return bw.safari ? true : rcube_event.cancel(e);
  };

  /**
   * Handler for window resize events
   */
  function onResize(e)
  {
    if (me.horizontal) {
      var new_height = parseInt(me.p2.parentNode.offsetHeight, 10) - parseInt(me.p2.style.top, 10);
      me.p2.style.height = (new_height > 0 ? new_height : 0) +'px';
    }
    else {
      var new_width = parseInt(me.p2.parentNode.offsetWidth, 10) - parseInt(me.p2.style.left, 10);
      me.p2.style.width = (new_width > 0 ? new_width : 0) + 'px';
    }
  };

  /**
   * Saves splitter position in cookie
   */
  this.set_cookie = function()
  {
    var exp = new Date();
    exp.setYear(exp.getFullYear() + 1);
    rcmail.set_cookie(this.id, this.pos, exp);
  };

} // end class rcube_splitter
