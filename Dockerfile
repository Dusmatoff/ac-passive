FROM alpine

# Install packages from testing repo's
RUN apk --no-cache add php7 php7-cli php7-json php7-openssl php7-curl

# Add application
COPY src/ /usr/src/app/
WORKDIR /usr/src/app
ENTRYPOINT ["php", "index.php"]