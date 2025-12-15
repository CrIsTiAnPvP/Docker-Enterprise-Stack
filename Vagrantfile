# -*- mode: ruby -*-
# vi: set ft=ruby :

Vagrant.configure('2') do |config|
  config.vm.box = "ubuntu/xenial64"
  config.ssh.forward_agent = true
  # config.ssh.private_key_path = ".ssh/id_ed2251"

  config.vm.define "router" do |rt|
    rt.vm.hostname = "router"
    
    # Rete 56.x: Conecta a DB, WEB, LDAP
    rt.vm.network "private_network", ip: "192.168.56.1", virtualbox__intnet: "intnet_56"
    
    # Rete 1.x: Conecta a DB, WEB, LDAP
    rt.vm.network "private_network", ip: "192.168.1.1", virtualbox__intnet: "intnet_1"
    
    # Interfaz hacia la red pública (host-only)
    rt.vm.network "public_network", ip: "192.168.69.254" 
  end

  config.vm.define "db" do |db|
    db.vm.hostname = "db"
    # Aseguramos la conexión con el router en ambas redes
    db.vm.network "private_network", ip: "192.168.56.2", virtualbox__intnet: "intnet_56"
    db.vm.network "private_network", ip: "192.168.1.2", virtualbox__intnet: "intnet_1"
  end

  config.vm.define "web" do |web|
    web.vm.hostname = "web"
    web.vm.network "private_network", ip: "192.168.56.3", virtualbox__intnet: "intnet_56"
    web.vm.network "private_network", ip: "192.168.1.3", virtualbox__intnet: "intnet_1"
  end

  config.vm.define "ldap" do |ldap|
    ldap.vm.hostname = "ldap"
    ldap.vm.network "private_network", ip: "192.168.56.4", virtualbox__intnet: "intnet_56"
    ldap.vm.network "private_network", ip: "192.168.1.4", virtualbox__intnet: "intnet_1"
  end
end