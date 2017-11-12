# Emoncms Device module

## Installation

The following steps document the installation of the device module on a stock emonpi/emonbase running the latest emonSD image.

After logging in via SSH, place the pi in write mode:

    rpi-rw
    
Navigate to the emoncms Modules folder:

    cd /var/www/emoncms/Modules
    
Clone the device module into the modules folder using git:

    git clone https://github.com/emoncms/device.git
    
Switch to the new device-integration branch of the device module

    cd device
    git checkout device-integration
    
Login to emoncms on your emonpi/emonbase, navigate to Setup > Administration, Update the emoncms database by running 'Update & Check' under the Update database section.

