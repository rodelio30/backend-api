server
{
    listen 80;
    server_name <API-LAN-IP>;
    index index.php index.html index.htm default.php default.htm default.html;
    root /www/wwwroot/backend-api/public;
    include /www/server/panel/vhost/nginx/extension/<API-LAN-IP>/*.conf;

    #SSL-START SSL related configuration, do NOT delete or modify the next line of commented-out 404 rules
    #error_page 404/404.html;
    #SSL-END

    #ERROR-PAGE-START  Error page configuration, allowed to be commented, deleted or modified
    error_page 404 /404.html;
    error_page 502 /502.html;
    #ERROR-PAGE-END

    #PHP-INFO-START  PHP reference configuration, allowed to be commented, deleted or modified
    include enable-php-83.conf;
    #PHP-INFO-END

    #REWRITE-START URL rewrite rule reference, any modification will invalidate the rewrite rules set by the panel
    include /www/server/panel/vhost/rewrite/<API-LAN-IP>.conf;
    #REWRITE-END

    # Forbidden files or directories
    location ~ ^/(\.user.ini|\.htaccess|\.git|\.env|\.svn|\.project|LICENSE|README.md)
    {
        return 404;
    }

    # Directory verification related settings for one-click application for SSL certificate
    location ~ \.well-known{
        allow all;
    }

    #Prohibit putting sensitive files in certificate verification directory
    if ( $uri ~ "^/\.well-known/.*\.(php|jsp|py|js|css|lua|ts|go|zip|tar\.gz|rar|7z|sql|bak)$" ) {
        return 403;
    }

    location ~ .*\.(gif|jpg|jpeg|png|bmp|swf)$
    {
        expires      30d;
        error_log /dev/null;
        access_log /dev/null;
    }
    location ~ .*\.(js|css)?$
    {
        expires      12h;
        error_log /dev/null;
        access_log /dev/null; 
    }
# API backend
location ^~ /api/ {

    # Directly enter the public folder (since root is already public/)
    try_files $uri /api/index.php?$query_string;

    # Explicit handler for API PHP files
    location ~ ^/api/.*\.php$ {
        fastcgi_pass unix:/tmp/php-cgi-83.sock;
        include fastcgi.conf;

        # Correct file mapping for CI4
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}




    location / {
    # Check if the file/directory exists. If not, rewrite to index.php
    try_files $uri $uri/ /index.php?$args;
}
 # This block passes all .php files to PHP-FPM
    location ~ \.php$ {
        # Check if the file exists within this location's root
        #try_files $uri =404;

        # Pass the request to PHP-FPM (adjust socket if necessary)
        fastcgi_pass unix:/tmp/php-cgi-83.sock;
        fastcgi_index index.php;
        include fastcgi.conf;
        
#        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;

	# You may need this if CI is running in a subdirectory or using custom config
	    # fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    access_log  /www/wwwlogs/<API-LAN-IP>.log;
    error_log  /www/wwwlogs/<API-LAN-IP>.error.log;
}