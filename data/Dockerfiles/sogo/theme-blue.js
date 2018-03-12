/* -*- Mode: javascript; indent-tabs-mode: nil; c-basic-offset: 2 -*- */

(function() {
  'use strict';

  angular.module('SOGo.Common')
    .config(configure)

  /**
   * @ngInject
   */
  configure.$inject = ['$mdThemingProvider'];
  function configure($mdThemingProvider) {

    /**
     * Define the Alternative theme
     */
    $mdThemingProvider.theme('mailcow')
      .primaryPalette('blue', {
        'default': '700',  // top toolbar
        'hue-1': '500',
        'hue-2': '700',    // sidebar toolbar
        'hue-3': 'A700'
      })
      .accentPalette('blue', {
        'default': '700',  // fab buttons
        'hue-1': '50',     // center list toolbar
        'hue-2': '600',
        'hue-3': 'A700'
      })
      .backgroundPalette('grey', {
        'default': '50',   // center list background
        'hue-1': '100',
        'hue-2': '200',
        'hue-3': '300'
      });
    $mdThemingProvider.theme('default')
      .primaryPalette('blue', {
        'default': '700',  // top toolbar
        'hue-1': '500',
        'hue-2': '700',    // sidebar toolbar
        'hue-3': 'A700'
      })
      .accentPalette('blue', {
        'default': '700',  // fab buttons
        'hue-1': '50',     // center list toolbar
        'hue-2': '600',
        'hue-3': 'A700'
      })
      .backgroundPalette('grey', {
        'default': '50',   // center list background
        'hue-1': '100',
        'hue-2': '200',
        'hue-3': '300'
      });

    $mdThemingProvider.setDefaultTheme('mailcow');
    $mdThemingProvider.generateThemesOnDemand(false);
  }
})();
