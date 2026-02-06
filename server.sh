# diplomasi-backend.ahmed-albakor.com
# diplomasi-$aY44ix2V5E*8CtA@bx&

ssh root@45.132.241.51


cd /
cd /home/ahmed-albakor-diplomasi-backend/htdocs/diplomasi-backend.ahmed-albakor.com
git pull

ssh root@45.132.241.51 'cd /; cd /home/ahmed-albakor-diplomasi-backend/htdocs/diplomasi-backend.ahmed-albakor.com; git pull'
ssh root@45.132.241.51 'cd /; cd /home/ahmed-albakor-diplomasi-backend/htdocs/diplomasi-backend.ahmed-albakor.com; git pull; php artisan migrate'




https://backend.diplomasi.app/

ssh root@76.13.143.214

cd /
cd home/diplomasi-backend/htdocs/backend.diplomasi.app
git pull

ssh root@76.13.143.214 'cd /; cd /home/diplomasi-backend/htdocs/backend.diplomasi.app; git pull;'





// setup laravel 11 project on vps server

1 .cloen the project
2. install composer : composer install
3. create database and user
4. create .env file
 - set database connection
 - set app key // php artisan key:generate
 - set app url
 - set app debug
 - set app timezone
 - set app locale
5. set Domin Root Directory to public folder
6. change permissions of storage/framework and storage/logs
7. run migrations // php artisan migrate
8. run seeders // php artisan db:seed
9. storage link // 
  // php artisan storage:link
  // change permissions of storage/app permissions to 777
  // chown root:root storage/app -R