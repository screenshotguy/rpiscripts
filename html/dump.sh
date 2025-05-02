echo "this is a web app i am building on top of a rasberry pi nano w2 running rasberry pi os lite" > out.txt

echo "index.html:" >> out.txt
cat index.html >> out.txt
echo "api/scan.php:" >> out.txt

cat api/scan.php >> out.txt
echo "api/connect.php:" >> out.txt
cat api/connect.php >> out.txt
