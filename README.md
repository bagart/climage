# Climage (Cloud Image)

install with LXC:

```shell

# create LXC container with name climage.lxc
sudo lxc-create -n climage.lxc -t ubuntu

#run this container
sudo lxc-start -n climage.lxc

#connect to container as root
sudo lxc-attach -n climage.lxc

#change default password
passwd ubuntu

#install requirements
apt-get install php7.0 composer beanstalkd php-imagick

#login with default username
su - ubuntu

# install climage
git clone https://github.com/bagart/climage.git
cd climage
composer update

# prepare config
cp .env.example .env
nano .env


# instructions for install google token : https://developers.google.com/drive/v3/web/quickstart/php
# 1. enable drive API https://console.developers.google.com/apis/library?project=api-project-6228863395
# 2. switch on oAuth2 https://developers.google.com/apis-explorer/?hl=ru#p/drive/v3/
# 3. create token https://console.developers.google.com/apis/credentials
# 4. write .env param in single line: CLIMAGE_GOOGLE_DRIVE_TOKEN


#prepare cloud token
src/Command/cloud_prepare.php



./src/Command/bot.php 
# Climage: Uploader Bot
# Usage:
# 	./src/Command/bot.php command [arguments]
# Available commands:
# 	status	Output current status in format %queue%:%number_of_images%
# 	schedule	Add filenames to resize queue
# 	resize	Resize next images from the queue
# 	upload	Upload next images to remote storage
# 	retry	Retry failed operations

#if you want install script as "bot" command
sudo ln -s /home/ubuntu/climage/src/Command/bot.php /usr/bin/bot

```


