# -*- mode: ruby -*-
# vi: set ft=ruby :

Vagrant.configure("2") do |config|
  config.vm.box = "ubuntu/xenial64"
  config.ssh.forward_agent = true
  config.ssh.private_key_path = "~/.ssh/id_ed2251"
  
  config.vm.define "router" do |rt|
    rt.vm.hostname = "router"
    rt.vm.network "private_network", ip: "192.168.33.1", virtualbox__intnet: "intnet"
    rt.vm.network "public_network"
  end

  config.vm.define "db" do |db|
    db.vm.hostname = "db"
    db.vm.network "private_network", ip: "192.168.33.2", virtualbox__intnet: "intnet"
  end

  config.vm.define "web" do |web|
    web.vm.hostname = "web"
    web.vm.network "private_network", ip: "192.168.33.3", virtualbox__intnet: "intnet"
  end

  config.vm.define "ldap" do |ldap|
    ldap.vm.hostname = "ldap"
    ldap.vm.network "private_network", ip: "192.168.33.4", virtualbox__intnet: "intnet"
  end
end
