#!/bin/bash

echo "Удаление старой машины judge0-mini, если она существует."
multipass delete judge0-mini || true
multipass purge

echo "Запуск новой машины judge0-mini."
SCRIPT_DIR=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" &> /dev/null && pwd)

echo "Запуск новой машины judge0-mini используя локальный образ..."

multipass launch "file://$SCRIPT_DIR/focal-server-cloudimg-amd64.img" --name judge0-mini --cpus 4 --memory 8G --disk 25G

echo "Машина judge0-mini запущена"

echo "Настройка GRUB."
multipass exec judge0-mini -- sudo bash -c '
  sed -i "s/^GRUB_CMDLINE_LINUX=\"\(.*\)\"/GRUB_CMDLINE_LINUX=\"\1 systemd.unified_cgroup_hierarchy=0\"/" /etc/default/grub
  update-grub
'
echo "Настройка произведена"

echo "Перезагрузка виртуальной машины для применения настроек ядра."
multipass restart judge0-mini

echo "Ожидание полного запуска машины (это может занять 20-30 секунд)..."
while ! multipass exec judge0-mini -- true >/dev/null 2>&1; do
  sleep 3
done
echo "Машина успешно перезагружена и готова к работе."


echo "Установка Docker и развертывание Judge0."
multipass exec judge0-mini -- bash -c '
  
  sudo apt-get update
  sudo apt-get install -y ca-certificates curl unzip openssl wget

  
  sudo install -m 0755 -d /etc/apt/keyrings
  sudo curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
  sudo chmod a+r /etc/apt/keyrings/docker.asc

  
  echo "Types: deb" | sudo tee /etc/apt/sources.list.d/docker.sources
  echo "URIs: https://download.docker.com/linux/ubuntu" | sudo tee -a /etc/apt/sources.list.d/docker.sources
  echo "Suites: $(. /etc/os-release && echo ${UBUNTU_CODENAME:-$VERSION_CODENAME})" | sudo tee -a /etc/apt/sources.list.d/docker.sources
  echo "Components: stable" | sudo tee -a /etc/apt/sources.list.d/docker.sources
  echo "Architectures: $(dpkg --print-architecture)" | sudo tee -a /etc/apt/sources.list.d/docker.sources
  echo "Signed-By: /etc/apt/keyrings/docker.asc" | sudo tee -a /etc/apt/sources.list.d/docker.sources

  
  sudo apt-get update
  sudo apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin

  
  sudo usermod -aG docker $USER

  
  wget https://github.com/judge0/judge0/releases/download/v1.13.1/judge0-v1.13.1.zip
  unzip judge0-v1.13.1.zip
  cd judge0-v1.13.1

  
  REDIS_PASS=$(openssl rand -hex 16)
  POSTGRES_PASS=$(openssl rand -hex 16)

  
  sed -i "s/^REDIS_PASSWORD=.*/REDIS_PASSWORD=$REDIS_PASS/" judge0.conf
  sed -i "s/^POSTGRES_PASSWORD=.*/POSTGRES_PASSWORD=$POSTGRES_PASS/" judge0.conf

  echo "Пароли успешно сгенерированы и добавлены в judge0.conf"

  # Запуск базы данных и Redis
  sudo docker compose up -d db redis
  
  echo "Ожидание инициализации БД."
  sleep 10
  
  # Запуск остальных сервисов Judge0
  sudo docker compose up -d
  
  echo "Ожидание финального запуска."
  sleep 5
'

echo "Развертывание завершено."


JUDGE0_IP=$(multipass info judge0-mini | grep IPv4 | awk '{print $2}')
echo "================================================="
echo "IP адрес машины judge0-mini: $JUDGE0_IP"
echo "API Judge0 доступно по адресу: http://$JUDGE0_IP:2358"
echo "================================================="
