# DigitalOcean Spaces for osTicket

## Installation

Download `storage-do.phar` from the
[latest release](https://github.com/vatsimnetwork/osticket-storage-do/releases/latest)
and place it in the `include/plugins` folder in your osTicket installation.

Sign into your osTicket staff control panel, enable the plugin, and create a
plugin instance. You'll then be able to configure the plugin.

## Configuration

You'll need to create a new bucket and API key pair in the DigitalOcean Control
Panel before proceeding.

* **Bucket Name**: Your Spaces bucket's name.
* **Region**: Your Spaces bucket's region.
* **ACL**: Whether to allow public read access to uploaded attachments.
* **Access Key**: Your Spaces API keypair's access key.
* **Secret Key**: Your Spaces API keypair's secret key.

Once configured, you can then switch to `DigitalOcean Spaces` in your osTicket's
system settings.
