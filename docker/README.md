# Docker
This setup is for the local development purpose, it builds two images: omekas and omekas_db.
## 1. Pakcage omeka
Run ./zip_omekas.sh to generate omeka-s.zip 
## 2. Setup database
Modify development.env and database.ini for db setup if necessary
## 3. Build and run docker
``` docker compose up --build ```
## 4. Test
http://localhost:8000

reference: https://github.com/giocomai/omeka-s-docker 
