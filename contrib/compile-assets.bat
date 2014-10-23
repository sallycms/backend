@echo off
call uglifyjs ../assets/js/json2.js -nc -m -c -o ../assets/js/json2.min.js
call uglifyjs ../assets/js/jquery.datetime.js -nc -m -c -o ../assets/js/jquery.datetime.min.js
call uglifyjs ../assets/js/standard.js -nc -m -c -o ../assets/js/standard.min.js
call uglifyjs ../assets/js/locales/de_de.js -nc -m -c -o ../assets/js/locales/de_de.min.js
call uglifyjs ../assets/js/locales/en_gb.js -nc -m -c -o ../assets/js/locales/en_gb.min.js
