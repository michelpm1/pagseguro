Plugin de inscrição via PagSeguro para o Moodle
-----------------------------------------------

The PagSeguro enrolment plugin allows you to set up paid courses.

The plugin has to be enabled by the site administrator and then added to the course by an administrator or manager.

You can then set an individual price for your course if needed.

This is an updated version based on https://moodle.org/plugins/view.php?plugin=enrol_pagseguro

Install
-------

You must put this code in the directory moodle/enrol/pagseguro
You can use git clone for this or download the latest version from github at https://github.com/danielneis/moodle-enrol_pagseguro/archive/update-3.0.zip

Configure
---------

* First, enable the plugin at Administration block > Site Administration > Plugins > Enrolments > Manage enrol plugins
* Then, go to its settings
* You must create a token at the PagSeguro website and use it to configure your Moodle plugin.
* Also, at the PagSeguro website, you should set the field "Código de transação para página de redirecionamento" with "transaction_id" (without quotes).
* Now you can go to any course and add the PagSeguro enrol method. There you will set the cost, currency and the email for the PagSeguro account that will be credited.

Dev Info
--------

[![Build Status](https://travis-ci.org/danielneis/moodle-enrol_pagseguro.svg?branch=update-3.0)](https://travis-ci.org/danielneis/moodle-enrol_pagseguro)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/danielneis/moodle-enrol_pagseguro/badges/quality-score.png?b=update-3.0)](https://scrutinizer-ci.com/g/danielneis/moodle-enrol_pagseguro/?branch=update-3.0)
