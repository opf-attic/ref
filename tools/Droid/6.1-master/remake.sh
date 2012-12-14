git rm -r lib droid-command-line-6.1.jar droid-ui-6.1.jar 
rm -fR lib droid-command-line-6.1.jar droid-ui-6.1.jar 
ln -s ../6.1-master/lib .
ln -s ../6.1-master/droid-command-line-6.1.jar .
ln -s ../6.1-master/droid-ui-6.1.jar .
/home/davetaz/ref/java/jre1.6.0_33/bin/java -jar droid-command-line-6.1.jar -x
