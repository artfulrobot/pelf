<?php
// This file declares an Angular module which can be autoloaded
// in CiviCRM. See also:
// \https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_angularModules/n
return array (
  'js' => 
  array (
    0 => 'ang/pelf.js',
    1 => 'ang/pelf/*.js',
    2 => 'ang/pelf/*/*.js',
  ),
  'css' => 
  array (
    0 => 'ang/pelf.css',
  ),
  'partials' => 
  array (
    0 => 'ang/pelf',
  ),
  'requires' => 
  array (
    0 => 'crmUi',
    1 => 'crmUtil',
    2 => 'ngRoute',
  ),
  'settings' => 
  array (
  ),
);
