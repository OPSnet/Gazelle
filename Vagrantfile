# -*- mode: ruby -*-
# vi: set ft=ruby :

Vagrant.configure("2") do |config|
  # All Vagrant configuration is done here. The most common configuration
  # options are documented and commented below. For a complete reference,
  # please see the online documentation at vagrantup.com.

  # Every Vagrant virtual environment requires a box to build off of.
  config.vm.box = "debian/contrib-stretch64"

  config.vm.synced_folder ".", "/var/www/",
    owner: "www-data"
  # only sync if it exists
  if File.directory?(File.expand_path("ocelot"))
      config.vm.synced_folder "ocelot/", "/var/ocelot"
  end

  config.vm.provision :shell, :path => ".vagrant/gazelle-setup.sh"

  # HTTP access
  config.vm.network :forwarded_port, guest: 80, host: 8080
  # MySQL access
  config.vm.network :forwarded_port, guest: 3306, host: 36000
  # Ocelot
  config.vm.network :forwarded_port, guest: 34000, host: 34000

  # Sometimes its useful to have a head to our VM (like if you wanted to
  # install EAC for the first time
  #config.vm.provider 'virtualbox' do |vb|
  #  vb.gui = true
  #end

  # This appears to fix a bug with the vbguest plugin so that it properly
  # installs instead of getting stuck at a user confirmation and dying
  if Vagrant.has_plugin? "vagrant-vbguest"
      config.vbguest.installer_arguments = ['--nox11 -- --force']
  end
end
