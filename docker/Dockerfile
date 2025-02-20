FROM php:8.2
RUN apt update && apt install -y unzip git make

RUN docker-php-ext-install mysqli pdo_mysql

RUN apt install -y locales
RUN sed -i -e 's/# en_US.UTF-8 UTF-8/en_US.UTF-8 UTF-8/' /etc/locale.gen && \
    dpkg-reconfigure --frontend=noninteractive locales && \
    update-locale LANG=en_US.UTF-8

RUN curl -s http://getcomposer.org/installer | php
RUN mv composer.phar /usr/local/bin/composer
