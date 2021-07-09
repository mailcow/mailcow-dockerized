!function ($) {

    "use strict";

    // TABCOLLAPSE CLASS DEFINITION
    // ======================

    var TabCollapse = function (el, options) {
        this.options   = options;
        this.$tabs  = $(el);

        this._accordionVisible = false; //content is attached to tabs at first
        this._initAccordion();
        this._checkStateOnResize();


        // checkState() has gone to setTimeout for making it possible to attach listeners to
        // shown-accordion.bs.tabcollapse event on page load.
        // See https://github.com/flatlogic/bootstrap-tabcollapse/issues/23
        var that = this;
        setTimeout(function() {
          that.checkState();
        }, 0);
    };

    TabCollapse.DEFAULTS = {
        accordionClass: 'visible-xs',
        tabsClass: 'hidden-xs',
        accordionTemplate: function(heading, groupId, parentId, active) {
            return  '<div class="panel panel-default">' +
                    '   <div class="panel-heading">' +
                    '      <h4 class="panel-title">' +
                    '      </h4>' +
                    '   </div>' +
                    '   <div id="' + groupId + '" class="panel-collapse collapse ' + (active ? 'in' : '') + '">' +
                    '       <div class="panel-body js-tabcollapse-panel-body">' +
                    '       </div>' +
                    '   </div>' +
                    '</div>'

        }
    };

    TabCollapse.prototype.checkState = function(){
        if (this.$tabs.is(':visible') && this._accordionVisible){
            this.showTabs();
            this._accordionVisible = false;
        } else if (this.$accordion.is(':visible') && !this._accordionVisible){
            this.showAccordion();
            this._accordionVisible = true;
        }
    };

    TabCollapse.prototype.showTabs = function(){
        var view = this;
        this.$tabs.trigger($.Event('show-tabs.bs.tabcollapse'));

        var $panelHeadings = this.$accordion.find('.js-tabcollapse-panel-heading').detach();

        $panelHeadings.each(function() {
            var $panelHeading = $(this),
            $parentLi = $panelHeading.data('bs.tabcollapse.parentLi');

            var $oldHeading = view._panelHeadingToTabHeading($panelHeading);

            $parentLi.removeClass('active');
            if ($parentLi.parent().hasClass('dropdown-menu') && !$parentLi.siblings('li').hasClass('active')) {
                $parentLi.parent().parent().removeClass('active');
            }

            if (!$oldHeading.hasClass('collapsed')) {
                $parentLi.addClass('active');
                $('.tab-pane').removeClass('active');
                $($panelHeading.attr('href')).addClass('active');
                if ($parentLi.parent().hasClass('dropdown-menu')) {
                    $parentLi.parent().parent().addClass('active');
                }
            } else {
                $oldHeading.removeClass('collapsed');
            }

            $parentLi.append($panelHeading);
        });

        if (!$('li').hasClass('active')) {
            $('li').first().addClass('active')
        }

        var $panelBodies = this.$accordion.find('.js-tabcollapse-panel-body');
        $panelBodies.each(function(){
            var $panelBody = $(this),
                $tabPane = $panelBody.data('bs.tabcollapse.tabpane');
            $tabPane.append($panelBody.contents().detach());
        });
        this.$accordion.html('');

        if(this.options.updateLinks) {
            var $tabContents = this.getTabContentElement();
            $tabContents.find('[data-toggle-was="tab"], [data-toggle-was="pill"]').each(function() {
                var $el = $(this);
                var href = $el.attr('href').replace(/-collapse$/g, '');
                $el.attr({
                    'data-toggle': $el.attr('data-toggle-was'),
                    'data-toggle-was': '',
                    'data-parent': '',
                    href: href
                });
            });
        }

        this.$tabs.trigger($.Event('shown-tabs.bs.tabcollapse'));
    };

    TabCollapse.prototype.getTabContentElement = function(){
        var $tabContents = $(this.options.tabContentSelector);
        if($tabContents.length === 0) {
            $tabContents = this.$tabs.siblings('.tab-content');
        }
        return $tabContents;
    };

    TabCollapse.prototype.showAccordion = function(){
        this.$tabs.trigger($.Event('show-accordion.bs.tabcollapse'));

        var $headings = this.$tabs.find('li:not(.dropdown) [data-toggle="tab"], li:not(.dropdown) [data-toggle="pill"]'),
            view = this;
        $headings.each(function(){
            var $heading = $(this),
                $parentLi = $heading.parent();
            $heading.data('bs.tabcollapse.parentLi', $parentLi);
            view.$accordion.append(view._createAccordionGroup(view.$accordion.attr('id'), $heading.detach()));
        });

        if(this.options.updateLinks) {
            var parentId = this.$accordion.attr('id');
            var $selector = this.$accordion.find('.js-tabcollapse-panel-body');
            $selector.find('[data-toggle="tab"], [data-toggle="pill"]').each(function() {
                var $el = $(this);
                var href = $el.attr('href') + '-collapse';
                $el.attr({
                    'data-toggle-was': $el.attr('data-toggle'),
                    'data-toggle': 'collapse',
                    'data-parent': '#' + parentId,
                    href: href
                });
            });
        }

        this.$tabs.trigger($.Event('shown-accordion.bs.tabcollapse'));
    };

    TabCollapse.prototype._panelHeadingToTabHeading = function($heading) {
        var href = $heading.attr('href').replace(/-collapse$/g, '');
        $heading.attr({
            'data-toggle': 'tab',
            'href': href,
            'data-parent': ''
        });
        return $heading;
    };

    TabCollapse.prototype._tabHeadingToPanelHeading = function($heading, groupId, parentId, active) {
        $heading.addClass('js-tabcollapse-panel-heading ' + (active ? '' : 'collapsed'));
        $heading.attr({
            'data-toggle': 'collapse',
            'data-parent': '#' + parentId,
            'href': '#' + groupId
        });
        return $heading;
    };

    TabCollapse.prototype._checkStateOnResize = function(){
        var view = this;
        $(window).resize(function(){
            clearTimeout(view._resizeTimeout);
            view._resizeTimeout = setTimeout(function(){
                view.checkState();
            }, 100);
        });
    };


    TabCollapse.prototype._initAccordion = function(){
        var randomString = function() {
            var result = "",
                possible = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
            for( var i=0; i < 5; i++ ) {
                result += possible.charAt(Math.floor(Math.random() * possible.length));
            }
            return result;
        };

        var srcId = this.$tabs.attr('id'),
            accordionId = (srcId ? srcId : randomString()) + '-accordion';

        this.$accordion = $('<div class="panel-group ' + this.options.accordionClass + '" id="' + accordionId +'"></div>');
        this.$tabs.after(this.$accordion);
        this.$tabs.addClass(this.options.tabsClass);
        this.getTabContentElement().addClass(this.options.tabsClass);
    };

    TabCollapse.prototype._createAccordionGroup = function(parentId, $heading){
        var tabSelector = $heading.attr('data-target'),
            active = $heading.data('bs.tabcollapse.parentLi').is('.active');

        if (!tabSelector) {
            tabSelector = $heading.attr('href');
            tabSelector = tabSelector && tabSelector.replace(/.*(?=#[^\s]*$)/, ''); //strip for ie7
        }

        var $tabPane = $(tabSelector),
            groupId = $tabPane.attr('id') + '-collapse',
            $panel = $(this.options.accordionTemplate($heading, groupId, parentId, active));
        $panel.find('.panel-heading > .panel-title').append(this._tabHeadingToPanelHeading($heading, groupId, parentId, active));
        $panel.find('.panel-body').append($tabPane.contents().detach())
            .data('bs.tabcollapse.tabpane', $tabPane);

        return $panel;
    };



    // TABCOLLAPSE PLUGIN DEFINITION
    // =======================

    $.fn.tabCollapse = function (option) {
        return this.each(function () {
            var $this   = $(this);
            var data    = $this.data('bs.tabcollapse');
            var options = $.extend({}, TabCollapse.DEFAULTS, $this.data(), typeof option === 'object' && option);

            if (!data) $this.data('bs.tabcollapse', new TabCollapse(this, options));
        });
    };

    $.fn.tabCollapse.Constructor = TabCollapse;


}(window.jQuery);