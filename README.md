# sane_php_frontend

This is a SANE frontend using scanimage shell utility. SANE PHP Frontend is a web user interface.

Version 0.2 contains essential functionality for getting scanners list, acquiring available resolutions and executing scan tasks.

Further versions will provide improved usability, some additional functionality and automatic deployment features.

## Deploy

1. Create `compose.yaml` from `compose.yaml.dist`
Edit ports for _php_ service if needed (you may want to place this app behind proxy)
2. Create `/etc/apache2/docker-backend.conf` from `/etc/apache2/docker-backend.conf.dist`
Edit _ServerAlias_ and _ServerAdmin_
3. Create `/etc/sane.d/net.conf` from `/etc/sane.d/net.conf.dist`
Edit saned hosts to addresses where your sane backend is placed. 
For further information on how to setup sane over network use this link https://wiki.debian.org/SaneOverNetwork#socket
4. `docker compose up -d --build`