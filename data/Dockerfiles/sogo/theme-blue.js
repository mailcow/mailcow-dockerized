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

    // Overwrite values to prevent flipping colors on login screen
    $mdThemingProvider.definePalette('mailcow-blue', {
      '50': 'E3F2FD',
      '100': 'BBDEFB',
      '200': '90CAF9',
      '300': '64B5F6',
      '400': '42A5F5',
      '500': '2196F3',
      '600': '1E88E5',
      '700': '1976D2',
      '800': '1565C0',
      '900': '0D47A1',
      '1000': '0D47A1',
      'A100': '82B1FF',
      'A200': '448AFF',
      'A400': '2979ff',
      'A700': '2962ff',
      'contrastDefaultColor': 'dark',
      'contrastLightColors': ['700', '800', '900'],
      'contrastDarkColors': undefined
    });

    $mdThemingProvider.definePalette('sogo-green', {
      '50': 'E3F2FD',
      '100': 'BBDEFB',
      '200': '90CAF9',
      '300': '64B5F6',
      '400': '42A5F5',
      '500': '2196F3',
      '600': '1E88E5',
      '700': '1976D2',
      '800': '1565C0',
      '900': '0D47A1',
      '1000': '0D47A1',
      'A100': '82B1FF',
      'A200': '448AFF',
      'A400': '2979ff',
      'A700': '2962ff',
      'contrastDefaultColor': 'dark',
      'contrastLightColors': ['700', '800', '900'],
      'contrastDarkColors': undefined
    });

    $mdThemingProvider.definePalette('default', {
      '50': 'E3F2FD',
      '100': 'BBDEFB',
      '200': '90CAF9',
      '300': '64B5F6',
      '400': '42A5F5',
      '500': '2196F3',
      '600': '1E88E5',
      '700': '1976D2',
      '800': '1565C0',
      '900': '0D47A1',
      '1000': '0D47A1',
      'A100': '82B1FF',
      'A200': '448AFF',
      'A400': '2979ff',
      'A700': '2962ff',
      'contrastDefaultColor': 'dark',
      'contrastLightColors': ['700', '800', '900'],
      'contrastDarkColors': undefined
    });

    $mdThemingProvider.theme('default')
      .primaryPalette('mailcow-blue', {
        'default': '700',  // top toolbar
        'hue-1': '500',
        'hue-2': '700',    // sidebar toolbar
        'hue-3': 'A200'
      })
      .accentPalette('mailcow-blue', {
        'default': '800',  // fab buttons
        'hue-1': '50',     // center list toolbar
        'hue-2': '500',
        'hue-3': 'A700'
      })
      .backgroundPalette('grey', {
        'default': '50',   // center list background
        'hue-1': '100',
        'hue-2': '200',
        'hue-3': '300'
      });

    $mdThemingProvider.setDefaultTheme('default');
    $mdThemingProvider.generateThemesOnDemand(false);
    $mdThemingProvider.alwaysWatchTheme(true);
  }
})();
