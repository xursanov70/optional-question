FROM nginx:latest

# nginx.conf faylni konteynerga copy qilamiz
COPY nginx.conf /etc/nginx/conf.d/default.conf

COPY . /var/www
