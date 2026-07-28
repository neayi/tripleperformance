```
chmod +x bin/block-scraper-ips.sh

sudo tee /etc/systemd/system/block-scraper-ips.service > /dev/null <<'EOF'
[Unit]
Description=Re-apply DOCKER-USER scraper IP block after Docker starts
After=docker.service
Requires=docker.service

[Service]
Type=oneshot
ExecStart=/var/www/tripleperformance/bin/block-scraper-ips.sh
RemainAfterExit=yes

[Install]
WantedBy=multi-user.target
EOF

sudo systemctl daemon-reload
sudo systemctl enable --now block-scraper-ips.service
```

Vérification :
```
sudo systemctl status block-scraper-ips.service
sudo iptables -L DOCKER-USER -n --line-numbers
```
