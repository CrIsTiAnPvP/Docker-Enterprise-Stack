# -*- mode: ruby -*-
# vi: set ft=ruby :

Vagrant.configure("2") do |config|
  config.vm.box = "ubuntu/xenial64"
  config.ssh.forward_agent = true
  config.ssh.private_key_path = ".ssh/id_ed2251"
  
  config.vm.define "router" do |rt|
    rt.vm.hostname = "router"
    rt.vm.network "private_network", ip: "192.168.33.1", virtualbox__intnet: "intnet"
    rt.vm.network "public_network", ip: "10.112.69.1"
    rt.vm.network "private_network", ip: "192.168.1.1"
  end

  config.vm.define "db" do |db|
    db.vm.hostname = "db"
    db.vm.network "private_network", ip: "192.168.33.2", virtualbox__intnet: "intnet"
    db.vm.network "private_network", ip: "192.168.1.2"
  end

  config.vm.define "web" do |web|
    web.vm.hostname = "web"
    web.vm.network "private_network", ip: "192.168.33.3", virtualbox__intnet: "intnet"
    web.vm.network "private_network", ip: "192.168.1.3"
  end

  config.vm.define "ldap" do |ldap|
    ldap.vm.hostname = "ldap"
    ldap.vm.network "private_network", ip: "192.168.33.4", virtualbox__intnet: "intnet"
    ldap.vm.network "private_network", ip: "192.168.1.4"
  end
end
