# IAGT API para PrestaShop

Este módulo añade una API REST personalizada a una tienda PrestaShop, permitiendo la integración de funcionalidades avanzadas con aplicaciones externas o frontales personalizados.

## 🚀 Características

- 🔐 Autenticación de usuarios vía JWT.
- 🛍️ Gestión de productos: listados, detalles, carruseles, más vendidos, etc.
- 🧾 Gestión de pedidos y proceso de checkout.
- 👥 Gestión de clientes: direcciones, carritos, wishlists.
- 🏷️ Gestión de marcas y sliders.
- 🌍 Configuración multidioma (config.xml en varios idiomas).
- 📦 Preparado para integrarse con servicios como MultiSafepay.

## 📁 Estructura de carpetas

iagtapi/
├── classes/
│ ├── service/
│ │ └── MultiSafepayService.php
│ └── webservice/
│ ├── auth/
│ ├── brands/
│ ├── company/
│ ├── customer/
│ ├── home/
│ ├── orders/
│ └── products/
├── config.php
├── iagtapi.php
├── sql/
├── views/
└── ...


## 🛠️ Instalación

1. Copiar el módulo `iagtapi` en el directorio `/modules` de tu tienda PrestaShop.
2. Instalar desde el panel de administración de PrestaShop.
3. Configurar los parámetros necesarios (tokens, rutas, permisos).
4. Consumir los endpoints desde el frontend o aplicaciones móviles.

## 📌 Requisitos

- PrestaShop 1.7+
- PHP 7.2 o superior

## 🧪 Tecnologías

- PHP
- JSON Web Tokens (JWT)
- Arquitectura RESTful
- PrestaShop Module System

## 🤝 Autor

Desarrollado por Íñigo Muñoz [LinkedIn](https://www.linkedin.com/in/imjdev/) | [GitHub](https://github.com/DevInigo)

