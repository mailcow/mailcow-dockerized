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
      .primaryPalette('green', {
        'default': '600',  // top toolbar
        'hue-1': '200',
        'hue-2': '600',    // sidebar toolbar
        'hue-3': 'A700'
      })
      .accentPalette('green', {
        'default': '600',  // fab buttons
        'hue-1': '50',     // center list toolbar
        'hue-2': '400',
        'hue-3': 'A700'
      })
      .backgroundPalette('grey', {
        'default': '50',   // center list background
        'hue-1': '50',
        'hue-2': '100',
        'hue-3': '100'
      });
    $mdThemingProvider.theme('default')
      .primaryPalette('green', {
        'default': '600',  // top toolbar
        'hue-1': '200',
        'hue-2': '600',    // sidebar toolbar
        'hue-3': 'A700'
      })
      .accentPalette('green', {
        'default': '600',  // fab buttons
        'hue-1': '50',     // center list toolbar
        'hue-2': '400',
        'hue-3': 'A700'
      })
      .backgroundPalette('grey', {
        'default': '50',   // center list background
        'hue-1': '50',
        'hue-2': '100',
        'hue-3': '100'
      });

    $mdThemingProvider.setDefaultTheme('mailcow');
    $mdThemingProvider.generateThemesOnDemand(false);
  }
})();
