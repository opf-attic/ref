git rm -r wd lib droid-command-line-5.0.3.jar droid-ui-5.0.3.jar
rm -fR wd lib droid-command-line-5.0.3.jar droid-ui-5.0.3.jar
ln -s ../5.0.3-master/wd .
ln -s ../5.0.3-master/lib .
ln -s ../5.0.3-master/droid-command-line-5.0.3.jar .
ln -s ../5.0.3-master/droid-ui-5.0.3.jar .
/home/davetaz/ref/java/jre1.6.0_33/bin/java -jar droid-command-line-5.0.3.jar -x
