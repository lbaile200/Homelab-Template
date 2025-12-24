#!/usr/bin/env bash
set -e

echo "== Enabling COPR repos =="
sudo dnf install -y dnf-plugins-core
sudo dnf copr enable -y codifryed/CoolerControl
sudo dnf copr enable -y emixampp/synology-drive
sudo dnf copr enable -y kylegospo/chatGPT-shell-cli
sudo dnf copr enable -y phracek/PyCharm

echo "== Installing RPM Fusion =="
sudo dnf install -y \
  https://mirrors.rpmfusion.org/free/fedora/rpmfusion-free-release-$(rpm -E %fedora).noarch.rpm \
  https://mirrors.rpmfusion.org/nonfree/fedora/rpmfusion-nonfree-release-$(rpm -E %fedora).noarch.rpm

echo "== Installing Google Chrome repo =="
sudo dnf install -y https://dl.google.com/linux/direct/google-chrome-stable_current_x86_64.rpm

echo "== Installing OpenRazer repo =="
sudo dnf config-manager --add-repo \
  https://download.opensuse.org/repositories/hardware:razer/Fedora_$(rpm -E %fedora)/hardware:razer.repo

echo "== Installing Mullvad repo =="
sudo tee /etc/yum.repos.d/mullvad.repo > /dev/null <<'EOF'
[mullvad-stable]
name=Mullvad VPN
baseurl=https://repository.mullvad.net/rpm/stable/$basearch
enabled=1
gpgcheck=1
gpgkey=https://repository.mullvad.net/rpm/mullvad-keyring.asc
EOF

echo "== Installing MySQL 8.4 LTS repos =="
sudo dnf install -y https://dev.mysql.com/get/mysql84-community-release-fc$(rpm -E %fedora)-1.noarch.rpm
sudo dnf config-manager --set-enabled \
  mysql-8.4-lts-community \
  mysql-connectors-community \
  mysql-tools-8.4-lts-community

echo "== Done =="
dnf repolist
