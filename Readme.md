🍔 DoorDash Clone – Cloud-Native 2-Tier Web Application on Azure
A fully functional DoorDash-inspired food delivery web app built as a DevOps capstone project on Microsoft Azure. Demonstrates real-world cloud infrastructure design including networking, compute, storage, database, and high availability.

🏗️ Architecture Overview
User
  ↓
Azure Public IP
  ↓
Azure Load Balancer
  ↓
VM Scale Set (Web VMs – Apache + PHP)
  ↓ (Private VNet)
MySQL DB VM (No Public IP)
  ↓
Azure Blob Storage (Restaurant Images)

🚀 Phases
Phase I – Network Foundation & Web Server

Created Azure Resource Group, Virtual Network, and Subnets
Deployed Linux VM (Ubuntu 22.04) with Apache
Configured NSG rules (HTTP port 80, SSH restricted to my IP)
Hosted a static HTML page to validate connectivity

Phase II – Dynamic 2-Tier Application (PHP + MySQL)

Deployed a private DB VM (no public IP) in a separate subnet
Installed and configured MySQL on the DB VM
Installed PHP on the Web VM and connected it to MySQL over private VNet
Built dynamic index.php that renders restaurant data from the database
Built admin.php to add restaurants via a web form

Phase III – Azure Blob Storage Integration

Created Azure Storage Account and Blob container with public read access
Modified admin.php to upload restaurant images directly to Blob Storage using Azure REST API
Stored Blob image URLs in MySQL — removed all dependency on local image storage
index.php now renders images directly from Blob Storage URLs stored in the DB

Phase IV – High Availability with VM Scale Set

Deprovisioned the Web VM and captured it as a Golden Image
Created a VM Scale Set from the custom image (2 instances)
Configured an Azure Load Balancer with health probes and load balancing rules
Enabled autoscaling — scales out at >70% CPU, scales in at <30% CPU
Verified traffic distribution across multiple instances using hostname display


🛠️ Azure Services Used
ServicePurposeResource GroupLogical container for all resourcesVirtual Network + SubnetsNetwork isolation (web-subnet, db-subnet)Network Security GroupFirewall rules for HTTP, SSH, MySQLLinux VMs (Ubuntu 22.04)Web server and Database serverApache + PHPWeb application hostingMySQLRelational database for restaurant dataAzure Blob StorageCloud image storageVM Scale SetHorizontal scaling of web tierAzure Load BalancerTraffic distribution across VMSS instancesPublic IPInternet-facing entry point

📁 Project File Structure
/var/www/html/          ← Web VM document root
├── index.php           ← DoorDash clone homepage (pulls data from MySQL + images from Blob)
├── admin.php           ← Admin panel (uploads image to Blob, inserts record into MySQL)
├── db.php              ← MySQL connection (connects to DB VM via private IP)
└── style.css           ← Frontend styling

⚙️ Key Configuration
db.php
Connects to MySQL on the DB VM using its private IP inside the Azure VNet:
php$pdo = new PDO("mysql:host=<DB_VM_PRIVATE_IP>;dbname=doordash_db", "user", "password");
Azure Blob Upload (admin.php)
Uses Azure Blob REST API with Storage Account Key — no SDK dependency:
php// PUT request to Azure Blob REST API
$upload_url = "https://<account>.blob.core.windows.net/<container>/<filename>";
// Authorization header signed with Storage Account Key
MySQL Schema
sqlCREATE TABLE restaurants (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(100) NOT NULL,
  category      VARCHAR(50)  NOT NULL,
  rating        DECIMAL(2,1) NOT NULL,
  distance_miles DECIMAL(4,1) NOT NULL,
  delivery_time VARCHAR(20)  NOT NULL,
  image_url     VARCHAR(500) NOT NULL,   -- Azure Blob URL
  delivery_fee  DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  created_at    DATETIME     DEFAULT NOW()
);

🔐 Security Design

DB VM has no public IP — accessible only within the VNet
MySQL port 3306 open only to VirtualNetwork traffic via NSG
SSH restricted to admin IP only
Storage Account Key used server-side only — never exposed to client


📈 High Availability Design

Web tier is stateless — no local image or session storage
Images in Blob Storage, data in DB VM → Web VM can be freely cloned
VMSS automatically replaces unhealthy instances via Load Balancer health probes
Autoscaling handles traffic spikes without manual intervention


🧰 Tech Stack

Cloud: Microsoft Azure
OS: Ubuntu 22.04 LTS
Web Server: Apache2
Language: PHP 8.x
Database: MySQL 8.x
Storage: Azure Blob Storage
Infra: Azure VMSS, Load Balancer, NSG, VNet


📌 How to Deploy

Create Resource Group, VNet with two subnets (web + db)
Deploy DB VM (no public IP), install MySQL, run setup.sql
Deploy Web VM, install Apache + PHP + php-curl
Copy index.php, admin.php, db.php, style.css to /var/www/html/
Update db.php with DB VM private IP and credentials
Update admin.php with Azure Storage Account name and Key
Test via Web VM public IP
For HA: deprovision VM → capture image → create VMSS → attach Load Balancer