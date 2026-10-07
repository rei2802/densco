-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: bngmyds7fold1emp22ud-mysql.services.clever-cloud.com:3306
-- Generation Time: Oct 07, 2026 at 03:50 PM
-- Server version: 8.0.22-13
-- PHP Version: 8.2.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `bngmyds7fold1emp22ud`
--

-- --------------------------------------------------------

--
-- Table structure for table `cart`
--

CREATE TABLE `cart` (
  `cart_id` int NOT NULL,
  `user_id` int NOT NULL,
  `product_id` int NOT NULL,
  `quantity` int NOT NULL DEFAULT '1',
  `added_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `order_id` int NOT NULL,
  `user_id` int NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `status` varchar(50) COLLATE utf8mb4_general_ci DEFAULT 'pending',
  `order_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fulfillment` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `address` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `phone` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `payment_method` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `proof` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`order_id`, `user_id`, `total_amount`, `status`, `order_date`, `fulfillment`, `address`, `phone`, `payment_method`, `proof`) VALUES
(2, 1, 3700.00, 'completed', '2026-09-25 17:02:06', 'Store Pickup', '', '09762704800', 'Cash', NULL),
(3, 1, 13000.00, 'completed', '2026-09-26 06:23:12', 'Store Pickup', '', '09762704800', 'Cash', NULL),
(4, 1, 6050.00, 'completed', '2026-09-26 07:13:54', 'Store Pickup', '', '09762704800', 'Cash', NULL),
(5, 2, 8635.00, 'completed', '2026-10-07 08:57:17', 'Delivery', 'sdadasd', '09234234234', 'Cash', NULL),
(6, 2, 2520.00, 'confirmed', '2026-10-07 09:16:40', 'Delivery', 'dsasdsa', '09234234234', 'Cash', NULL),
(7, 2, 2520.00, 'shipped', '2026-10-07 09:55:19', 'Delivery', '34234', '09643545', 'Cash', NULL),
(8, 2, 2520.00, 'pending', '2026-10-07 10:01:45', 'Delivery', '12a pablo st', '09234234234', 'Cash', NULL),
(9, 2, 5152.00, 'pending', '2026-10-07 13:26:47', 'Delivery', 'fedda', '09234234234', 'Cash', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `order_item_id` int NOT NULL,
  `order_id` int NOT NULL,
  `product_id` int NOT NULL,
  `quantity` int NOT NULL,
  `price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`order_item_id`, `order_id`, `product_id`, `quantity`, `price`) VALUES
(14, 6, 9, 4, 600.00),
(15, 7, 9, 4, 600.00),
(16, 8, 9, 4, 600.00),
(17, 9, 9, 2, 600.00),
(18, 9, 13, 1, 3432.00),
(19, 9, 18, 2, 200.00);

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `product_id` int NOT NULL,
  `product_name` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `price` decimal(10,2) NOT NULL,
  `stock_quantity` int DEFAULT '0',
  `category` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `image_path` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `hidden` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`product_id`, `product_name`, `description`, `price`, `stock_quantity`, `category`, `image_path`, `created_at`, `hidden`) VALUES
(9, 'Nebulizer Machine', 'For asthma', 600.00, 8, 'Diagnostics', 'assets/products/product_6ab77fc457c19.png', '2026-09-26 07:08:09', 1),
(12, 'Stretcher Ambulance Painted', 'dimension: 16 x 71 x 22 in', 1000.00, 10, 'Stretcher / Ambulance', 'assets/products/product_6ac61c4193939.png', '2026-10-07 10:17:37', 0),
(13, 'Stretcher Ambulance Painted', 'DIMENSIONS:\r\n16 x 71 x 22 in', 3432.00, 31, 'Stretcher / Ambulance', 'assets/products/product_6ac6649c199af.jpg', '2026-10-07 10:25:36', 0),
(14, 'Stretcher Canvass', 'Stretcher Canvass - manufactured by Densco Health Product (Stretcher)\r\n75 x 22 in', 1222.00, 20, 'Stretcher', 'assets/products/product_6ac6234aea057.jpg', '2026-10-07 10:43:31', 0),
(15, 'Stretcher ER Stainless', 'Stretcher ER Stainless - manufactured by Densco Health Product (Stretcher / ER)\r\n32 x 74 x 24 in', 2000.00, 39, 'Stretcher', 'assets/products/product_6ac6247084d6d.jpg', '2026-10-07 10:52:32', 0),
(16, 'Stretcher ER Painted', 'Stretcher ER Painted - manufactured by Densco Health Product (Stretcher / ER)\r\n32 x 74 x 24 in', 830.00, 30, 'Stretcher', 'assets/products/product_6ac6258761eec.jpg', '2026-10-07 10:56:43', 0),
(17, 'Treatment Table Painted', 'Treatment Table Painted - manufactured by Densco Health Product (Table)\r\n28 x 20 x 34.5 in', 300.00, 30, 'Table', 'assets/products/product_6ac6263eaa32e.jpg', '2026-10-07 11:00:14', 0),
(18, 'Utility Cart 2 Layer Stainless', 'Stainless steel medical utility cart with 2 open shelves, a side push handle, and smooth-rolling caster wheels for stable, easy movement of tools and supplies. Manufactured by Densco Health Products.\r\n80 x 50 x 75 in', 200.00, 18, 'Cart', 'assets/products/product_6ac626e76346b.jpg', '2026-10-07 11:03:03', 0),
(19, 'Utility Cart 3 Layer Stainless', 'Stainless steel medical utility cart with 3-tiered shelves (safety rails), side handle, and durable caster wheels for organized storage and stable transport. Manufactured by Densco Health Products.\r\n36 x 19.5 x 29 in', 900.00, 20, 'Cart', 'assets/products/product_6ac6276d728c9.jpg', '2026-10-07 11:04:51', 0),
(20, 'UV Light 4Bulb', 'UV Light 4Bulb - manufactured by Densco Health Product (Lighting)\r\n12 x 12 x 62 in', 100.00, 20, 'Lighting', 'assets/products/product_6ac628049fdbb.jpg', '2026-10-07 11:07:48', 0),
(21, 'Panel Screen Double Stainless', 'Panel Screen Double Stainless - manufactured by Densco Health Product (Panel Screen)\r\n85 x 65 in', 200.00, 39, 'Panel Screen', 'assets/products/product_6ac628b2913a4.jpg', '2026-10-07 11:10:42', 0),
(22, 'Panel Screen Single Painted', 'Panel Screen Double Stainless - manufactured by Densco Health Product (Panel Screen) \r\n85 x 65 in', 1200.00, 20, 'Panel Screen', 'assets/products/product_6ac62923860da.jpg', '2026-10-07 11:12:35', 0),
(23, 'Panel Screen Single Stainless', 'Panel Screen Single Stainless - manufactured by Densco Health Product (Panel Screen)\r\n85 x 65 in', 4000.00, 20, 'Panel Screen', 'assets/products/product_6ac6298f12356.jpg', '2026-10-07 11:14:23', 0),
(24, 'Panel Screen Triple Stainless', 'Panel Screen Triple Stainless - manufactured by Densco Health Product (Panel Screen)\r\n122 x 65 in', 1000.00, 40, 'Panel Screen', 'assets/products/product_6ac629e674aa8.jpg', '2026-10-07 11:15:50', 0),
(25, 'Panel Screen Triple Painted', 'Panel Screen Triple Painted - manufactured by Densco Health Products (Panel Screen)\r\n122 x 65 in', 2000.00, 13, 'Panel Screen', 'assets/products/product_6ac62a3b8f85c.jpg', '2026-10-07 11:17:15', 0),
(26, 'Revolving Stool Painted', 'Revolving Stool Painted - manufactured by Densco Health Product (Stool)\r\n24.5 x 13 in', 21000.00, 12, 'Stool', 'assets/products/product_6ac62a9c7323b.jpg', '2026-10-07 11:18:52', 0),
(27, 'Revolving Stool Stainless', 'Revolving Stool Stainless - manufactured by Densco Health Product (Stool)', 4000.00, 40, 'Stool', 'assets/products/product_6ac62b3916c01.jpg', '2026-10-07 11:21:29', 0),
(28, 'Scrub Sink 304 Stainless', 'Scrub Sink 304 Stainless - manufactured by Densco Health Product (Sink)\r\n22 x 24 x 35 in', 5001.00, 20, 'Sink', 'assets/products/product_6ac62b9d71d2d.jpg', '2026-10-07 11:23:09', 0),
(29, 'Stirr-Up Stainless', 'Stirr-Up Stainless - manufactured by Densco Health Product (OB Accessory)', 1000.00, 30, 'Diagnostics', 'assets/products/product_6ac62be0d5961.jpg', '2026-10-07 11:24:16', 1),
(30, 'Oxygen Cart 50lbs Double Painted', 'Oxygen Cart 50lbs Double Painted - manufactured by Densco Health Product (Oxygen Cart)\r\n24 x 41 in', 3000.00, 230, 'Oxygen Cart', 'assets/products/product_6ac62c2dc0556.jpg', '2026-10-07 11:25:33', 0),
(31, 'Oxygen Cart 50lbs Single Stainless', 'Oxygen Cart 50lbs Single Stainless - manufactured by Densco Health Product (Oxygen Cart)', 30000.00, 23, 'Oxygen Cart', 'assets/products/product_6ac62c7440ef2.jpg', '2026-10-07 11:26:44', 0),
(32, 'Oxygen Cart 50lbs Single Painted', 'Oxygen Cart 50lbs Single Painted - manufactured by Densco Health Product (Oxygen Cart)\r\n12 x 39.5 in', 300.00, 30, 'Oxygen Cart', 'assets/products/product_6ac631e0a3c12.jpg', '2026-10-07 11:29:08', 0),
(33, 'Oxygen Cart 5lbs Painted', 'Oxygen Cart 5lbs Painted - manufactured by Densco Health Product (Oxygen Cart)\r\n43 x 8.5 x 5.5 in', 4000.00, 60, 'Oxygen Cart', 'assets/products/product_6ac630f4d50f9.jpg', '2026-10-07 11:30:27', 0),
(34, 'Oxygen Cart 5lbs Stainless', 'Oxygen Cart 5lbs Stainless - manufactured by Densco Health Product (Oxygen Cart)\r\n43 x 8.5 x 5.5 in', 3000.00, 40, 'Oxygen Cart', 'assets/products/product_6ac6332aa5aec.jpg', '2026-10-07 11:31:12', 0),
(35, 'Oxygen Holder 50lbs Stainless', 'Oxygen Holder 50lbs Stainless - manufactured by Densco Health Product (Oxygen Holder)\r\n21 x 9 in', 300.00, 23, 'Oxygen Holder', 'assets/products/product_6ac6335a99ffb.jpg', '2026-10-07 11:56:10', 0),
(36, 'Oxygen Holder 50lbs Stainless', 'Oxygen Holder 50lbs Stainless - manufactured by Densco Health Product (Oxygen Holder)', 4000.00, 13, 'Oxygen Holder', 'assets/products/product_6ac6412265475.jpg', '2026-10-07 12:07:04', 0),
(37, 'Pail Stainless', 'Pail Stainless - manufactured by Densco Health Product (Basin/Pail)\r\n20 x 24 x 27 cm', 222.00, 33, 'Basin/Pail', 'assets/products/product_6ac6415158383.jpg', '2026-10-07 12:08:00', 0),
(38, 'Panel Screen Double Painted', 'Panel Screen Double Painted - manufactured by Densco Health Product (Panel Screen)\r\n85 x 65 in', 8888.00, 90, 'Panel Screen', 'assets/products/product_6ac64181d03e0.jpg', '2026-10-07 12:08:42', 0),
(39, 'Negatoscope Double Stainless', 'Negatoscope Double Stainless - manufactured by Densco Health Product (Negatoscope)\r\n30 x 19.5 x 3 in', 555.00, 56, 'Negatoscope', 'assets/products/product_6ac641d7c17ae.jpg', '2026-10-07 12:09:33', 0),
(40, 'Negatoscope Single Painted', 'Negatoscope Single Painted - manufactured by Densco Health Product (Negatoscope)\r\n3 x 20 x 16 in', 5000.00, 55, 'Negatoscope', 'assets/products/product_6ac6425a989d7.jpg', '2026-10-07 12:10:14', 0),
(41, 'Negatoscope Single Stainless', 'Negatoscope Single Stainless - manufactured by Densco Health Product (Negatoscope)', 45.00, 233, 'Negatoscope', 'assets/products/product_6ac6432999b65.jpg', '2026-10-07 12:10:32', 0),
(42, 'Cadaver Stretcher Stainless', 'Cadaver Stretcher Stainless - manufactured by Densco Health Product (Stretcher)\r\n24 x 74 x 33 cm', 4599.00, 45, 'Stretcher', 'assets/products/product_6ac642b816ab8.jpg', '2026-10-07 12:11:46', 0),
(43, 'Oxygen Cart 15lbs Painted', 'Oxygen Cart 15lbs Painted - manufactured by Densco Health Product (Oxygen Cart) 44 x 8.5 x 6.5 in', 500.00, 88, 'Oxygen Cart', 'assets/products/product_6ac6437459ca3.jpg', '2026-10-07 12:12:27', 0),
(44, 'Oxygen Cart 15lbs Stainless', 'Oxygen Cart 15lbs Stainless - manufactured by Densco Health Product (Oxygen Cart)', 6789.00, 33, 'Oxygen Cart', 'assets/products/product_6ac6447e5b68e.jpg', '2026-10-07 12:12:50', 0),
(45, 'Oxygen Cart 10lbs Painted', 'Oxygen Cart 10lbs Painted - manufactured by Densco Health Product (Oxygen Cart)\r\n44 x 8.5 x 6.5 in', 3000.00, 34, 'Oxygen Cart', 'assets/products/product_6ac644f1e0b1a.jpg', '2026-10-07 12:13:25', 0),
(46, 'Oxygen Cart 10lbs Stainless', 'Oxygen Cart 10lbs Stainless - manufactured by Densco Health Product (Oxygen Cart)', 45555.00, 45, 'Oxygen Cart', 'assets/products/product_6ac6452886b6c.jpg', '2026-10-07 12:19:30', 0),
(47, 'Oxygen Cart 50lbs Double Stainless', 'Oxygen Cart 50lbs Double Stainless - manufactured by Densco Health Product (Oxygen Cart)\r\n24 x 41 in', 2333.00, 33, 'Oxygen Cart', 'assets/products/product_6ac6455e707bc.jpg', '2026-10-07 12:20:06', 0),
(48, 'Mayo Stand 2Wheels Stainless', 'Mayo Stand 2Wheels Stainless - manufactured by Densco Health Product (Mayo Stand) \r\n36 x 16 x 12 in', 0.00, 13, 'Mayo Stand', 'assets/products/product_6ac64591e543a.jpg', '2026-10-07 12:20:45', 0),
(49, 'Mayo Stand 4Wheels Painted', 'Mayo Stand 4Wheels Painted - manufactured by Densco Health Product (Mayo Stand)\r\n36 x 16 x 12 in', 4555.00, 45, 'Mayo Stand', 'assets/products/product_6ac645c233212.jpg', '2026-10-07 12:21:11', 0),
(50, 'Mayo Stand 4Wheels Stainless', 'Mayo Stand 4Wheels Stainless - manufactured by Densco Health Product (Mayo Stand)\r\n36 x 16 x 12 in', 6700.00, 23, 'Mayo Stand', 'assets/products/product_6ac645f4453e4.jpg', '2026-10-07 12:21:48', 0),
(51, 'Mayo Tray Stainless', 'Mayo Tray Stainless - manufactured by Densco Health Product (Mayo Tray)\r\n16 x 12 in', 3400.00, 99, 'Mayo Tray', 'assets/products/product_6ac646254b1c2.jpg', '2026-10-07 12:22:23', 0),
(52, 'Medicine Cabinet Single Painted', 'Medicine Cabinet Single Painted - manufactured by Densco Health Product (Cabinet)', 3455.00, 23, 'Cabinet', 'assets/products/product_6ac6464f5efb9.jpg', '2026-10-07 12:22:43', 0),
(53, 'Medicine Cabinet Single Stainless', 'Medicine Cabinet Single Stainless - manufactured by Densco Health Product (Cabinet)', 2344.00, 25, 'Cabinet', 'assets/products/product_6ac648a89ce7c.png', '2026-10-07 12:23:02', 0),
(54, 'Medicine Cabinet Double Painted', 'Medicine Cabinet Double Painted - manufactured by Densco Health Product (Cabinet)\r\n39 x 14 x 61 in', 34555.00, 24, 'Cabinet', 'assets/products/product_6ac648e615ed2.jpg', '2026-10-07 12:24:33', 0),
(55, 'Medicine Cabinet Double Stainless', 'Medicine Cabinet Double Stainless - manufactured by Densco Health Product (Cabinet)', 4600.00, 34, 'Cabinet', 'assets/products/product_6ac64907546da.jpg', '2026-10-07 12:25:14', 0),
(56, 'Negatoscope Double Painted', 'Negatoscope Double Painted - manufactured by Densco Health Product (Negatoscope)', 44.00, 45, 'Negatoscope', 'assets/products/product_6ac64958a3c68.jpg', '2026-10-07 12:25:30', 0),
(57, 'Food Conveyor 50trays Stainless', 'Food Conveyor 50trays Stainless - manufactured by Densco Health Product (Food Conveyor)\r\n18 x 45 x 56 cm', 8777.00, 98, 'Food Conveyor', 'assets/products/product_6ac6497c6df2e.jpg', '2026-10-07 12:25:54', 0),
(58, 'Foot Stool Double Painted', 'Foot Stool Double Painted - manufactured by Densco Health Product (Stool)\r\n60 x 35 x 40 cm', 5666.00, 78, 'Stool', 'assets/products/product_6ac649e351b26.jpg', '2026-10-07 12:26:22', 0),
(59, 'Foot Stool Double Stainless', 'Foot Stool Double Stainless - manufactured by Densco Health Product (Stool)\r\n60 x 35 x 40 cm', 5656.00, 56, 'Stool', 'assets/products/product_6ac64a866b052.jpg', '2026-10-07 12:26:44', 0),
(60, 'Foot Stool Single Painted', 'Foot Stool Single Painted - manufactured by Densco Health Product (Stool)', 7887.00, 76, 'Stool', 'assets/products/product_6ac64abb75a36.jpg', '2026-10-07 12:27:06', 0),
(61, 'Foot Stool Single Stainless', 'Foot Stool Single Stainless - manufactured by Densco Health Product (Stool)\r\n22 x 25 x 35 cm', 555.00, 44, 'Stool', 'assets/products/product_6ac64af3b4572.jpg', '2026-10-07 12:27:34', 0),
(62, 'Hospital Bed 3 Cranks', 'Hospital Bed 3 Cranks - manufactured by Densco Health Product (Hospital Bed)\r\n23 x 35 x 80 in', 54453.00, 34, 'Hospital Bed', 'assets/products/product_6ac64b3927806.jpg', '2026-10-07 12:28:02', 0),
(63, 'Hospital Bed 2 Cranks Local', 'Hospital Bed 2 Cranks Local - manufactured by Densco Health Product (Hospital Bed)', 454.00, 49, 'Hospital Bed', 'assets/products/product_6ac64bdc1636c.jpg', '2026-10-07 12:28:20', 0),
(64, 'Hospital Bed Pedia w/ Foam w/ Wheels', 'Hospital Bed Pedia w/ Foam w/ Wheels - manufactured by Densco Health Product (Hospital Bed)\r\n70 x 30 x 19 in', 4546.00, 434, 'Hospital Bed', 'assets/products/product_6ac64c318092f.jpg', '2026-10-07 12:28:46', 0),
(65, 'Instrument Cabinet Stainless', 'Instrument Cabinet Stainless - manufactured by Densco Health Product (Cabinet)', 5765.00, 56, 'Cabinet', 'assets/products/product_6ac64c5f73905.jpg', '2026-10-07 12:29:03', 0),
(66, 'Hamilton Type Stainless', 'Hamilton Type Stainless - manufactured by Densco Health Product (OB/Delivery Table)\r\n58 x 20 x 34 in', 4548.00, 88, 'Diagnostics', 'assets/products/product_6ac64cc0454cc.jpg', '2026-10-07 12:29:23', 1),
(67, 'OB-Type Painted', 'OB-Type Painted - manufactured by Densco Health Product (OB/Delivery Table)\r\n36 x 72 x 20 in', 2999.00, 55, 'Diagnostics', 'assets/products/product_6ac64d0550c68.jpg', '2026-10-07 12:29:54', 1),
(68, 'OB-Type Stainless', 'OB-Type Stainless - manufactured by Densco Health Product (OB/Delivery Table)', 5666.00, 5655, 'Diagnostics', 'assets/products/product_6ac64d92b5f1b.jpg', '2026-10-07 12:30:11', 1),
(69, 'Senn-Type Painted', 'Senn-Type Painted - manufactured by Densco Health Product (OB/Delivery Table)', 6790.00, 23, 'Diagnostics', 'assets/products/product_6ac64dd74e544.jpg', '2026-10-07 12:30:30', 1),
(70, 'Senn-Type Stainless', 'Senn-Type Stainless - manufactured by Densco Health Product (OB/Delivery Table)\r\n35 x 72 x 20 in', 4545.00, 77, 'Diagnostics', 'assets/products/product_6ac64ded156e3.jpg', '2026-10-07 12:30:53', 1),
(71, 'Extraction Chair Stainless', 'Extraction Chair Stainless - manufactured by Densco Health Product (Chair)\r\n17 x 21 x 19 in', 5479.00, 99, 'Chair', 'assets/products/product_6ac64e28849a9.jpg', '2026-10-07 12:31:28', 0),
(72, 'Food Conveyor 20trays Painted', 'Food Conveyor 20trays Painted - manufactured by Densco Health Product (Food Conveyor)', 5656.00, 676, 'Food Conveyor', 'assets/products/product_6ac64e3820450.jpg', '2026-10-07 12:32:29', 0),
(73, 'Food Conveyor 20trays Stainless', 'Food Conveyor 20trays Stainless - manufactured by Densco Health Product (Food Conveyor)\r\n33 x 27 x 16.5 in', 567.00, 23, 'Food Conveyor', 'assets/products/product_6ac64e762336c.jpg', '2026-10-07 12:33:01', 0),
(74, 'Food Conveyor 30trays Stainless', 'Food Conveyor 30trays Stainless - manufactured by Densco Health Product (Food Conveyor)\r\n20 x 32 x 50 in', 1111.00, 246, 'Food Conveyor', 'assets/products/product_6ac64e825a7c4.jpg', '2026-10-07 12:33:23', 0),
(75, 'Droplight w/ Cover Stainless', 'Droplight w/ Cover Stainless - manufactured by Densco Health Product (Lighting)', 445.00, 89, 'Lighting', 'assets/products/product_6ac64eb621c21.jpg', '2026-10-07 12:33:45', 0),
(76, 'Droplight w/ Wheels Stainless', 'Droplight w/ Wheels Stainless - manufactured by Densco Health Product (Lighting)', 555.00, 76, 'Lighting', 'assets/products/product_6ac64ee664fc3.jpg', '2026-10-07 12:34:00', 0),
(77, 'Droplight w/o Wheels Stainless', 'Droplight w/o Wheels Stainless - manufactured by Densco Health Product (Lighting)', 4555.00, 42, 'Lighting', NULL, '2026-10-07 12:34:50', 0),
(78, 'Emergency Cart Painted', 'Painted-finish emergency cart with multiple drawers and a lower cabinet with doors; caster wheels for mobility. Manufactured by Densco Health Product.', 3499.00, 152536, 'Cart', NULL, '2026-10-07 12:35:19', 0),
(79, 'Emergency Cart Stainless', 'Stainless steel emergency cart with multiple drawers, a lower cabinet with doors, an IV pole attachment, and a side waste bin holder; caster wheels for mobility. Manufactured by Densco Health Product.', 346.00, 56, 'Cart', NULL, '2026-10-07 12:35:42', 0),
(80, 'Emergency Cart w/ Cardiac Board', 'Emergency Cart w/ Cardiac Board - manufactured by Densco Health Product (Cart)', 4544.00, 152540, 'Cart', NULL, '2026-10-07 12:36:09', 0),
(81, 'ECG Table Painted', 'ECG Table Painted - manufactured by Densco Health Product (Table)', 3434.00, 967, 'Table', NULL, '2026-10-07 12:36:25', 0),
(82, 'ECG Table Stainless', 'ECG Table Stainless - manufactured by Densco Health Product (Table)\r\n76 x 46 x 36 in', 3432.00, 33, 'Table', NULL, '2026-10-07 12:36:50', 0),
(83, 'Hamilton Type Painted', 'Hamilton Type Painted - manufactured by Densco Health Product (OB/Delivery Table)', 4545.00, 767, 'Diagnostics', NULL, '2026-10-07 12:37:07', 1),
(84, 'Bedpan Plastic', 'Bedpan Plastic - manufactured by Densco Health Product (Bedpan)\r\n14 x 8.5 x 2.5 in', 666.00, 13, 'Bedpan', NULL, '2026-10-07 12:37:26', 0),
(85, 'Bedside Cabinet Stainless', 'Bedside Cabinet Stainless - manufactured by Densco Health Product (Cabinet)', 4545.00, 6768, 'Hospital Bed', NULL, '2026-10-07 12:37:52', 0),
(86, 'Bedside Cabinet Painted', 'Bedside Cabinet Painted - manufactured by Densco Health Product (Cabinet)\r\n31 x 19 x 16 in', 4546.00, 342, 'Hospital Bed', NULL, '2026-10-07 12:38:34', 0),
(87, 'Bedside Table w/ Wheels Painted', 'Bedside Table w/ Wheels Painted - manufactured by Densco Health Product (Table)\r\n31 x 19 x 16 in', 4545.00, 66, 'Hospital Bed', NULL, '2026-10-07 12:38:57', 0),
(88, 'Bedside Table w/ Wheels Stainless', 'Bedside Table w/ Wheels Stainless - manufactured by Densco Health Product (Table)', 3247.00, 345, 'Hospital Bed', NULL, '2026-10-07 12:39:12', 0),
(89, 'Bedside Table w/o Wheels Painted', 'Bedside Table w/o Wheels Painted - manufactured by Densco Health Product (Table)', 455.00, 432, 'Hospital Bed', NULL, '2026-10-07 12:39:42', 0),
(90, 'Bedside Table w/o Wheels Stainless', 'Bedside Table w/o Wheels Stainless - manufactured by Densco Health Product (Table)\r\n31 x 19 x 16 in', 7890.00, 67, 'Hospital Bed', NULL, '2026-10-07 12:40:10', 0),
(91, 'Chart Holder 24 Capacity Painted', 'Chart Holder 24 Capacity Painted - manufactured by Densco Health Product (Chart Holder)\r\n22 x 33.5 x 23 in', 4322.00, 45, 'Chart Holder', NULL, '2026-10-07 12:40:35', 0),
(92, 'Chart Holder 24 Cap. Stainless', 'Chart Holder 24 Cap. Stainless - manufactured by Densco Health Product (Chart Holder)\r\n22 x 33.5 x 23 in', 4576.00, 432, 'Chart Holder', NULL, '2026-10-07 12:41:00', 0),
(93, 'Instrument Cabinet Painted', 'Instrument Cabinet Painted - manufactured by Densco Health Product (Cabinet)\r\n16 x 31 x 60 in', 2346.00, 565, 'Cabinet', NULL, '2026-10-07 12:41:20', 0),
(94, 'IV Pole Stainless', 'IV Pole Stainless - manufactured by Densco Health Product (IV Stand)', 7654.00, 45, 'Diagnostics', NULL, '2026-10-07 12:41:38', 1),
(95, 'IV Stand 2 Hooks Stainless', 'IV Stand 2 Hooks Stainless - manufactured by Densco Health Product (IV Stand)\r\n37 x 60 x 13 in', 5734.00, 235, 'IV Stand', NULL, '2026-10-07 12:42:00', 0),
(96, 'IV Stand 4Hooks Stainless', 'IV Stand 4Hooks Stainless - manufactured by Densco Health Product (IV Stand)', 3456.00, 443, 'IV Stand', NULL, '2026-10-07 12:42:13', 0),
(97, 'Kick Bucket Painted', 'Kick Bucket Painted - manufactured by Densco Health Product (Bucket)\r\n13 x 10 x 11 cm', 6756.00, 11, 'Bucket', NULL, '2026-10-07 12:42:33', 0),
(98, 'Kick Bucket Stainless', 'Kick Bucket Stainless - manufactured by Densco Health Product (Bucket)\r\n13 x 10 x 11 cm', 56784.00, 6788, 'Bucket', NULL, '2026-10-07 12:42:59', 0),
(99, 'Linen Hamper Painted', 'Linen Hamper Painted - manufactured by Densco Health Product (Hamper)\r\n20 x 20 x 36 in', 345.00, 44, 'Hamper', NULL, '2026-10-07 12:43:26', 0),
(100, 'Linen Hamper Stainless', 'Linen Hamper Stainless - manufactured by Densco Health Product (Hamper)', 8897.00, 43, 'Hamper', NULL, '2026-10-07 12:43:41', 0),
(101, 'Mayo Stand 2 Wheels Painted', 'Mayo Stand 2 Wheels Painted - manufactured by Densco Health Product (Mayo Stand)', 233543.00, 3243, 'Mayo Stand', NULL, '2026-10-07 12:43:56', 0),
(102, 'Anesthesia Table Painted', 'Anesthesia Table Painted - manufactured by Densco Health Product (Table) \r\n14 x 20 x 37 in', 465543.00, 45, 'Table', NULL, '2026-10-07 12:44:18', 0),
(103, 'Anesthesia Table Stainless', 'Anesthesia Table Stainless - manufactured by Densco Health Product (Table)', 33.00, 3453, 'Table', NULL, '2026-10-07 12:44:31', 0),
(104, 'Autoclave Machine 280L', 'Autoclave Machine 280L - manufactured by Densco Health Product (Sterilizer)', 54634.00, 55, 'Diagnostics', NULL, '2026-10-07 12:44:50', 1),
(105, 'Baby Bassinet Basket', 'Baby Bassinet Basket - manufactured by Densco Health Product (Baby Bassinet)', 3422.00, 2134, 'Baby Bassinet', NULL, '2026-10-07 12:45:04', 0),
(106, 'Baby Bassinet Foam', 'Baby Bassinet Foam - manufactured by Densco Health Product (Baby Bassinet)\r\n26.5 x 14 x 2 in', 6445.00, 456, 'Baby Bassinet', NULL, '2026-10-07 12:45:46', 0),
(107, 'Baby Bassinet w/ Cabinet Sliding Door', 'Baby Bassinet w/ Cabinet Sliding Door - manufactured by Densco Health Product (Baby Bassinet)', 45345345.00, 44, 'Baby Bassinet', NULL, '2026-10-07 12:46:04', 0),
(108, 'Baby Crib w/ Wheels Painted', 'Baby Crib w/ Wheels Painted - manufactured by Densco Health Product (Baby Crib)\r\n48 x 19 x 25 in', 33333.00, 56, 'Baby Crib', NULL, '2026-10-07 12:46:30', 0),
(109, 'Baby Bassinet Stand Painted', 'Baby Bassinet Stand Painted - manufactured by Densco Health Product (Baby Bassinet) \r\n35.5 x 71.1 x 20.3 cm', 3453453.00, 2354, 'Baby Bassinet', NULL, '2026-10-07 12:46:57', 0),
(110, 'Baby Bassinet Stand Stainless', 'Baby Bassinet Stand Stainless - manufactured by Densco Health Product (Baby Bassinet)', 3434.00, 34534, 'Baby Bassinet', NULL, '2026-10-07 12:47:14', 0),
(111, 'Chart Holder 12 Capacity Painted', 'Chart Holder 12 Capacity Painted - manufactured by Densco Health Product (Chart Holder)', 3432.00, 2345, 'Chart Holder', NULL, '2026-10-07 12:50:31', 0),
(112, 'Chart Holder 12 Capacity Stainless', 'Chart Holder 12 Capacity Stainless - manufactured by Densco Health Product (Chart Holder)', 342.00, 55, 'Chart Holder', NULL, '2026-10-07 12:50:45', 0),
(113, 'Circular Table Painted', 'Circular Table Painted - manufactured by Densco Health Product (Table)', 2344.00, 2323, 'Table', NULL, '2026-10-07 12:50:58', 0),
(114, 'Circular Table Stainless', 'Circular Table Stainless - manufactured by Densco Health Product (Table)', 23432.00, 121, 'Table', NULL, '2026-10-07 12:51:12', 0),
(115, 'Dental Cabinet Stainless', 'Dental Cabinet Stainless - manufactured by Densco Health Product (Cabinet)', 65743.00, 343, 'Cabinet', NULL, '2026-10-07 12:51:30', 0),
(116, 'Dialysis Chair Stainless', 'Dialysis Chair Stainless - manufactured by Densco Health Product (Chair)', 4444.00, 213, 'Chair', NULL, '2026-10-07 12:51:44', 0),
(117, 'Dressing Carriage Painted', 'Dressing Carriage Painted - manufactured by Densco Health Product (Cart)', 3242.00, 33, 'Diagnostics', NULL, '2026-10-07 12:51:56', 1),
(118, 'Dressing Carriage w/ Pail Painted', 'Dressing Carriage w/ Pail Painted - manufactured by Densco Health Product (Cart) \r\n30.5 x 16 x 30 in', 324.00, 323, 'Basin/Pail', NULL, '2026-10-07 12:52:17', 0);

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

CREATE TABLE `staff` (
  `staff_id` int NOT NULL,
  `first_name` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `last_name` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `role` varchar(50) COLLATE utf8mb4_general_ci DEFAULT 'staff',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff`
--

INSERT INTO `staff` (`staff_id`, `first_name`, `last_name`, `email`, `password`, `role`, `created_at`) VALUES
(1, 'Densco', 'Owner', 'owner@densco.com', '$2y$10$u6Hp7UuXKqBIPyn9CIhrZ.kqDP1iX6LFT05XHLwGA2fVqwzNki.6i', 'owner', '2026-10-07 09:53:21'),
(2, 'Densco', 'Admin', 'admin@densco.com', '$2y$10$yZVaDZjmwO0Q5shfQOyeMu72SDzXSOoWl7BZ3Ts5y8pSDS1qOKDiW', 'admin', '2026-10-07 09:53:21'),
(3, 'Densco', 'Inventory', 'inventory@densco.com', '$2y$10$AY59dKmLk4CVMsufNlCFGOYbfaFQvvMRSqh7FnhuZ9QaJCuHyRHl2', 'inventory', '2026-10-07 09:53:21');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int NOT NULL,
  `full_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `phone` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `address` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `full_name`, `email`, `password`, `phone`, `address`, `created_at`) VALUES
(1, 'Reika Nakayama', 'nakayamareika46@gmail.com', '$2y$10$L/h2OGb6so4jQ5VHD8rSl.tfFjJUNS59eLDk5OrKGxnNjDQ56luWi', NULL, NULL, '2026-09-25 15:51:22'),
(2, 'Mark Ryan Pardilla', 'markpardilla66@gmail.com', '$2y$10$i9ncw8eJPZFc1CGp8nP/DeZ8hVXizv8qtAznArV5HfPK2LJtmcn82', NULL, NULL, '2026-10-07 08:56:49');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`cart_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`order_item_id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`product_id`);

--
-- Indexes for table `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`staff_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `cart_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `order_item_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `product_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=122;

--
-- AUTO_INCREMENT for table `staff`
--
ALTER TABLE `staff`
  MODIFY `staff_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `cart`
--
ALTER TABLE `cart`
  ADD CONSTRAINT `cart_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cart_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
