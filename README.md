# partio-ohjelma.fi frontend wordpress

https://partio-ohjelma.fi

## Environment variables

This project uses [PHP dotenv](https://github.com/vlucas/phpdotenv) to read environment variables from .env files. To setup the project add the .env file into the project root (for example /var/www/partio.dev/.env).

Here is an example file for a development environment:

```
DB_NAME=partioxdev
DB_USER=partioxdev
DB_PASSWORD=partioxdev
DB_HOST=localhost
 
# Set this to 1 to disable Redis usage.
WP_REDIS_DISABLE=0
WP_REDIS_DB=0
WP_REDIS_HOST=127.0.0.1
 
# Generate your keys here: https://roots.io/salts.html
AUTH_KEY='Sg5Dr5?F^;=q;.3KO:(Fc}Dutp!ama`lT64b_f?^a.2Z{4d!-0&l-:[MvL/U&]+c'
SECURE_AUTH_KEY='nW{=r(0|@T?.i[+w/@kT+Y^A2W^Up+q/.F?S@{x8PaW$YOoVfPX&p%{gOlJrOyO^'
LOGGED_IN_KEY='<1<XIN$v7|$V0gLxz[H:R]9/s&fCK+03y|GN3^Af[[0Oe({vwie:G|5Z8H;#xyD4'
NONCE_KEY='-hL.?f|xYlYTnnrsXZt,_*:{YZly}T*<fbM$7A-2x!=nml)a|v|d)0$zZ7>{e1@.'
AUTH_SALT='}ZiR0PX$h.|N3Pd?v+CFaz)9|}M*!1^qp.d,/ld+O=@[F5pbLTImpRFD!{/3y8=!'
SECURE_AUTH_SALT='5SqS*s=mcP3ICkIzvTEbvmaApj%OEaXCf;OTWPKrb=q@yoIt%N|CJ(Ywso>{VUX;'
LOGGED_IN_SALT='6Oh4?t_<!zr|ACPqJ!CP:UE3YCB[9{2u908x5d`O(N?7lJ{D)yl/y5bOVj[F?Ub1'
NONCE_SALT='vE.x!#FnY43Q_=*{wSY9|jp9JXo5XouOoJX7=9v>OpV>kB@0b8b<,T)dG8cJ!`9r'
```