-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: bngmyds7fold1emp22ud-mysql.services.clever-cloud.com:3306
-- Generation Time: Oct 09, 2026 at 05:58 AM
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
(9, 2, 5152.00, 'pending', '2026-10-07 13:26:47', 'Delivery', 'fedda', '09234234234', 'Cash', NULL),
(10, 3, 30120.00, 'pending', '2026-10-08 11:20:08', 'Delivery', '3erddfas', '9559 805 493', 'Cash', NULL);

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
(19, 9, 18, 2, 200.00),
(20, 10, 18, 1, 5800.00),
(21, 10, 19, 1, 6200.00),
(22, 10, 20, 1, 18000.00);

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
(17, 'Treatment Table Painted', 'Treatment Table Painted - manufactured by Densco Health Product (Table)\r\n28 x 20 x 34.5 in', 6600.00, 30, 'Table', 'assets/products/product_6ac6263eaa32e.jpg', '2026-10-07 11:00:14', 1),
(18, 'Utility Cart 2 Layer Stainless', 'Stainless steel medical utility cart with 2 open shelves, a side push handle, and smooth-rolling caster wheels for stable, easy movement of tools and supplies. Manufactured by Densco Health Products.\r\n80 x 50 x 75 in', 5800.00, 17, 'Cart', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791426857/densco/products/fxihvkgix5rodgn92h8q.jpg', '2026-10-07 11:03:03', 0),
(19, 'Utility Cart 3 Layer Stainless', 'Stainless steel medical utility cart with 3-tiered shelves (safety rails), side handle, and durable caster wheels for organized storage and stable transport. Manufactured by Densco Health Products.\r\n36 x 19.5 x 29 in', 6200.00, 19, 'Cart', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791426875/densco/products/avvw0myztweh5yvbkbgp.jpg', '2026-10-07 11:04:51', 0),
(20, 'UV Light 4Bulb', 'UV Light 4Bulb - manufactured by Densco Health Product (Lighting)\r\n12 x 12 x 62 in', 18000.00, 19, 'Lighting', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791426899/densco/products/bo5noozky6yfko2mfsqn.jpg', '2026-10-07 11:07:48', 0),
(21, 'Panel Screen Double Stainless', 'Panel Screen Double Stainless - manufactured by Densco Health Product (Panel Screen)\r\n85 x 65 in', 3800.00, 39, 'Panel Screen', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791426928/densco/products/sckvcmo74bcpkrcgsrlq.jpg', '2026-10-07 11:10:42', 0),
(22, 'Panel Screen Single Painted', 'Panel Screen Double Stainless - manufactured by Densco Health Product (Panel Screen) \r\n85 x 65 in', 1700.00, 20, 'Panel Screen', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791426992/densco/products/kwtdpz2xfz8cz3l0kpzk.jpg', '2026-10-07 11:12:35', 0),
(23, 'Panel Screen Single Stainless', 'Panel Screen Single Stainless - manufactured by Densco Health Product (Panel Screen)\r\n85 x 65 in', 2000.00, 20, 'Panel Screen', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791427023/densco/products/kfex384b9uc23ywcb8ug.jpg', '2026-10-07 11:14:23', 0),
(24, 'Panel Screen Triple Stainless', 'Panel Screen Triple Stainless - manufactured by Densco Health Product (Panel Screen)\r\n122 x 65 in', 4900.00, 40, 'Panel Screen', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791427053/densco/products/zg7lppcoobf1mxgzcjgf.jpg', '2026-10-07 11:15:50', 0),
(25, 'Panel Screen Triple Painted', 'Panel Screen Triple Painted - manufactured by Densco Health Products (Panel Screen)\r\n122 x 65 in', 3200.00, 13, 'Panel Screen', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791427068/densco/products/kmwfm6eg4e4qqfizscnp.jpg', '2026-10-07 11:17:15', 0),
(26, 'Revolving Stool Painted', 'Revolving Stool Painted - manufactured by Densco Health Product (Stool)\r\n24.5 x 13 in', 21000.00, 12, 'Stool', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791427105/densco/products/snbzn72p2b2elnmjvdv8.jpg', '2026-10-07 11:18:52', 0),
(27, 'Revolving Stool Stainless', 'Revolving Stool Stainless - manufactured by Densco Health Product (Stool)', 4000.00, 40, 'Stool', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791427141/densco/products/pfsmsk3zb5jmrsrdgiy6.jpg', '2026-10-07 11:21:29', 0),
(28, 'Scrub Sink 304 Stainless', 'Scrub Sink 304 Stainless - manufactured by Densco Health Product (Sink)\r\n22 x 24 x 35 in', 23800.00, 20, 'Sink', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791427172/densco/products/hhg0nfrqi6idllmdh7lr.jpg', '2026-10-07 11:23:09', 0),
(29, 'Stirr-Up Stainless', 'Stirr-Up Stainless - manufactured by Densco Health Product (OB Accessory)', 400.00, 30, 'Sink', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791427183/densco/products/runigccv2biacymwbixx.jpg', '2026-10-07 11:24:16', 1),
(30, 'Oxygen Cart 50lbs Double Painted', 'Oxygen Cart 50lbs Double Painted - manufactured by Densco Health Product (Oxygen Cart)\r\n24 x 41 in', 7000.00, 230, 'Oxygen Cart', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791427203/densco/products/s4vxvh3udoofgmcoivli.jpg', '2026-10-07 11:25:33', 0),
(31, 'Oxygen Cart 50lbs Single Stainless', 'Oxygen Cart 50lbs Single Stainless - manufactured by Densco Health Product (Oxygen Cart)', 4700.00, 23, 'Oxygen Cart', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791427233/densco/products/b5pyhtjclziigvyyl29z.jpg', '2026-10-07 11:26:44', 0),
(32, 'Oxygen Cart 50lbs Single Painted', 'Oxygen Cart 50lbs Single Painted - manufactured by Densco Health Product (Oxygen Cart)\r\n12 x 39.5 in', 3500.00, 30, 'Oxygen Cart', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791427246/densco/products/pncst0cbuuntshzwqest.jpg', '2026-10-07 11:29:08', 0),
(33, 'Oxygen Cart 5lbs Painted', 'Oxygen Cart 5lbs Painted - manufactured by Densco Health Product (Oxygen Cart)\r\n43 x 8.5 x 5.5 in', 1000.00, 60, 'Oxygen Cart', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791427290/densco/products/smqob1qzo1datsmne94r.jpg', '2026-10-07 11:30:27', 0),
(34, 'Oxygen Cart 5lbs Stainless', 'Oxygen Cart 5lbs Stainless - manufactured by Densco Health Product (Oxygen Cart)\r\n43 x 8.5 x 5.5 in', 1800.00, 40, 'Oxygen Cart', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791427346/densco/products/qs1f9v10arczgnbaytmc.jpg', '2026-10-07 11:31:12', 0),
(35, 'Oxygen Holder 50lbs Stainless', 'Oxygen Holder 50lbs Painted- manufactured by Densco Health Product (Oxygen Holder)\r\n21 x 9 in', 2300.00, 23, 'Oxygen Holder', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791427370/densco/products/g6o2kmsuavg0qivd55mr.jpg', '2026-10-07 11:56:10', 0),
(36, 'Oxygen Holder 50lbs Painted', 'Oxygen Holder 50lbs Stainless - manufactured by Densco Health Product (Oxygen Holder)', 3900.00, 13, 'Oxygen Holder', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791427454/densco/products/ghdpc4jh5hfzw9dwsraw.jpg', '2026-10-07 12:07:04', 0),
(37, 'Pail Stainless', 'Pail Stainless - manufactured by Densco Health Product (Basin/Pail)\r\n20 x 24 x 27 cm', 600.00, 33, 'Basin/Pail', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791427476/densco/products/ya3mei8uz8qldq2u9vul.jpg', '2026-10-07 12:08:00', 0),
(38, 'Panel Screen Double Painted', 'Panel Screen Double Painted - manufactured by Densco Health Product (Panel Screen)\r\n85 x 65 in', 2400.00, 90, 'Panel Screen', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791427498/densco/products/xhg3jiljmkzsfogeedkr.jpg', '2026-10-07 12:08:42', 0),
(39, 'Negatoscope Double Stainless', 'Negatoscope Double Stainless - manufactured by Densco Health Product (Negatoscope)\r\n30 x 19.5 x 3 in', 5000.00, 56, 'Negatoscope', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791427523/densco/products/yh9tq72wmjiusaxzoayq.jpg', '2026-10-07 12:09:33', 0),
(40, 'Negatoscope Single Painted', 'Negatoscope Single Painted - manufactured by Densco Health Product (Negatoscope)\r\n3 x 20 x 16 in', 1900.00, 55, 'Negatoscope', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791427536/densco/products/vnnkexl7n58itsjj038k.jpg', '2026-10-07 12:10:14', 0),
(41, 'Negatoscope Single Stainless', 'Negatoscope Single Stainless - manufactured by Densco Health Product (Negatoscope)', 3800.00, 233, 'Negatoscope', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791427586/densco/products/djuhgf7bfn5zwmmjvhwe.jpg', '2026-10-07 12:10:32', 0),
(42, 'Cadaver Stretcher Stainless', 'Cadaver Stretcher Stainless - manufactured by Densco Health Product (Stretcher)\r\n24 x 74 x 33 cm', 4599.00, 45, 'Stretcher', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791427597/densco/products/comfkt7a8z6uonvapbdy.jpg', '2026-10-07 12:11:46', 1),
(43, 'Oxygen Cart 15lbs Painted', 'Oxygen Cart 15lbs Painted - manufactured by Densco Health Product (Oxygen Cart) 44 x 8.5 x 6.5 in', 1100.00, 88, 'Oxygen Cart', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791427662/densco/products/eqzk0uxahj6xbmdp5eiy.jpg', '2026-10-07 12:12:27', 0),
(44, 'Oxygen Cart 15lbs Stainless', 'Oxygen Cart 15lbs Stainless - manufactured by Densco Health Product (Oxygen Cart)', 2000.00, 33, 'Oxygen Cart', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791427679/densco/products/fzywe7zgv4fpspmu6xwn.jpg', '2026-10-07 12:12:50', 0),
(45, 'Oxygen Cart 20lbs Painted', 'Oxygen Cart 10lbs Painted - manufactured by Densco Health Product (Oxygen Cart)', 1100.00, 34, 'Oxygen Cart', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791427742/densco/products/sd4agzybshrq4mcrvcoe.jpg', '2026-10-07 12:13:25', 0),
(46, 'Oxygen Cart 20lbs Stainless', 'Oxygen Cart 10lbs Stainless - manufactured by Densco Health Product (Oxygen Cart)', 2000.00, 45, 'Oxygen Cart', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791427761/densco/products/ypimkxru1zxls3kjrydq.jpg', '2026-10-07 12:19:30', 0),
(47, 'Oxygen Cart 50lbs Single Stainless', 'Oxygen Cart 50lbs Single Stainless - manufactured by Densco Health Product (Oxygen Cart)', 4700.00, 33, 'Oxygen Cart', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791427792/densco/products/dz1a3mhmwrptxotsxc84.jpg', '2026-10-07 12:20:06', 0),
(48, 'Mayo Stand 2Wheels Stainless', 'Mayo Stand 2Wheels Stainless - manufactured by Densco Health Product (Mayo Stand) \r\n36 x 16 x 12 in', 2000.00, 13, 'Mayo Stand', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791427810/densco/products/vvcmlredl1wgomp1oec0.jpg', '2026-10-07 12:20:45', 0),
(49, 'Mayo Stand 4Wheels Painted', 'Mayo Stand 4Wheels Painted - manufactured by Densco Health Product (Mayo Stand)\r\n36 x 16 x 12 in', 1500.00, 45, 'Mayo Stand', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791427837/densco/products/zu4cdge10pynfxrabwbj.jpg', '2026-10-07 12:21:11', 0),
(50, 'Mayo Stand 4Wheels Stainless', 'Mayo Stand 4Wheels Stainless - manufactured by Densco Health Product (Mayo Stand)\r\n36 x 16 x 12 in', 2200.00, 23, 'Mayo Stand', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791427858/densco/products/avhtwffrr0lturxi0gfd.jpg', '2026-10-07 12:21:48', 0),
(51, 'Mayo Tray Stainless', 'Mayo Tray Stainless - manufactured by Densco Health Product (Mayo Tray)\r\n16 x 12 in', 450.00, 99, 'Mayo Tray', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791427867/densco/products/sirazrunrrjpbzzjszck.jpg', '2026-10-07 12:22:23', 0),
(52, 'Medicine Cabinet Single Painted', 'Medicine Cabinet Single Painted - manufactured by Densco Health Product (Cabinet)', 5000.00, 23, 'Cabinet', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791427878/densco/products/gcrxrktaqf3tu4yuangg.jpg', '2026-10-07 12:22:43', 0),
(53, 'Medicine Cabinet Single Stainless', 'Medicine Cabinet Single Stainless - manufactured by Densco Health Product (Cabinet)', 9500.00, 25, 'Cabinet', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791428085/densco/products/sqqhzllnel4vstgmhxkh.jpg', '2026-10-07 12:23:02', 0),
(54, 'Medicine Cabinet Double Painted', 'Medicine Cabinet Double Painted - manufactured by Densco Health Product (Cabinet)\r\n39 x 14 x 61 in', 9800.00, 24, 'Cabinet', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791428067/densco/products/wnuznvb9jwkhnljvwwam.jpg', '2026-10-07 12:24:33', 0),
(55, 'Medicine Cabinet Double Stainless', 'Medicine Cabinet Double Stainless - manufactured by Densco Health Product (Cabinet)', 12800.00, 34, 'Cabinet', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791428100/densco/products/m1xwenajrnqtw4wgdb91.jpg', '2026-10-07 12:25:14', 0),
(56, 'Negatoscope Double Painted', 'Negatoscope Double Painted - manufactured by Densco Health Product (Negatoscope)', 3200.00, 45, 'Negatoscope', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791428141/densco/products/me2psnjilsbuib8ijoaq.jpg', '2026-10-07 12:25:30', 0),
(57, 'Food Conveyor 50trays Stainless', 'Food Conveyor 50trays Stainless - manufactured by Densco Health Product (Food Conveyor)\r\n18 x 45 x 56 cm', 48000.00, 98, 'Food Conveyor', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791428159/densco/products/cgc5dpxf5qj3wgqu6nau.jpg', '2026-10-07 12:25:54', 0),
(58, 'Foot Stool Double Painted', 'Foot Stool Double Painted - manufactured by Densco Health Product (Stool)\r\n60 x 35 x 40 cm', 1400.00, 78, 'Stool', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791428181/densco/products/xha5ofpy9wxbijrsdkbp.jpg', '2026-10-07 12:26:22', 0),
(59, 'Foot Stool Double Stainless', 'Foot Stool Double Stainless - manufactured by Densco Health Product (Stool)\r\n60 x 35 x 40 cm', 1800.00, 56, 'Stool', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791428215/densco/products/h4a4fwhruqgcoakvobmd.jpg', '2026-10-07 12:26:44', 0),
(60, 'Foot Stool Single Painted', 'Foot Stool Single Painted - manufactured by Densco Health Product (Stool)', 600.00, 76, 'Stool', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791428235/densco/products/ywxyjgwcfculrcgrmuyr.jpg', '2026-10-07 12:27:06', 0),
(61, 'Foot Stool Single Stainless', 'Foot Stool Single Stainless - manufactured by Densco Health Product (Stool)\r\n22 x 25 x 35 cm', 1150.00, 44, 'Stool', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791428248/densco/products/nudtldmb6aa7ay2wtql6.jpg', '2026-10-07 12:27:34', 0),
(62, 'Hospital Bed 3 Cranks', 'Hospital Bed 3 Cranks - manufactured by Densco Health Product (Hospital Bed)\r\n23 x 35 x 80 in', 13708.00, 34, 'Hospital Bed', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791428263/densco/products/xptjpgsczmkkql6fycak.jpg', '2026-10-07 12:28:02', 0),
(63, 'Hospital Bed 2 Cranks Local', 'Hospital Bed 2 Cranks Local - manufactured by Densco Health Product (Hospital Bed)', 6300.00, 49, 'Hospital Bed', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791428295/densco/products/ey14f4fsrggevnmpdsjk.jpg', '2026-10-07 12:28:20', 0),
(64, 'Hospital Bed Pedia w/ Foam w/ Wheels', 'Hospital Bed Pedia w/ Foam w/ Wheels - manufactured by Densco Health Product (Hospital Bed)\r\n70 x 30 x 19 in', 6800.00, 434, 'Hospital Bed', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791428323/densco/products/pjhrznqoawhkancbwyw8.jpg', '2026-10-07 12:28:46', 0),
(65, 'Instrument Cabinet Stainless', 'Instrument Cabinet Stainless - manufactured by Densco Health Product (Cabinet)', 19800.00, 56, 'Cabinet', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791428336/densco/products/gvsev2wvqgykvocajl4n.jpg', '2026-10-07 12:29:03', 0),
(66, 'Hamilton Type Stainless', 'Hamilton Type Stainless - manufactured by Densco Health Product (OB/Delivery Table)\r\n58 x 20 x 34 in', 4548.00, 88, 'Diagnostics', 'assets/products/product_6ac64cc0454cc.jpg', '2026-10-07 12:29:23', 1),
(67, 'OB-Type Painted', 'OB-Type Painted - manufactured by Densco Health Product (OB/Delivery Table)\r\n36 x 72 x 20 in', 5100.00, 55, 'OB/Delivery Table', 'assets/products/product_6ac64d0550c68.jpg', '2026-10-07 12:29:54', 1),
(68, 'OB-Type Stainless', 'OB-Type Stainless - manufactured by Densco Health Product (OB/Delivery Table)', 10800.00, 5655, 'OB/Delivery Table', 'assets/products/product_6ac64d92b5f1b.jpg', '2026-10-07 12:30:11', 1),
(69, 'Senn-Type Painted', 'Senn-Type Painted - manufactured by Densco Health Product (OB/Delivery Table)', 5200.00, 23, 'Hospital Bed', 'assets/products/product_6ac64dd74e544.jpg', '2026-10-07 12:30:30', 1),
(70, 'Senn-Type Stainless', 'Senn-Type Stainless - manufactured by Densco Health Product (OB/Delivery Table)\r\n35 x 72 x 20 in', 10300.00, 77, 'Hospital Bed', 'assets/products/product_6ac64ded156e3.jpg', '2026-10-07 12:30:53', 1),
(71, 'Extraction Chair Stainless', 'Extraction Chair Stainless - manufactured by Densco Health Product (Chair)\r\n17 x 21 x 19 in', 14500.00, 99, 'Chair', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791428391/densco/products/yxlaknrrvapesknkehdx.jpg', '2026-10-07 12:31:28', 0),
(72, 'Food Conveyor 20trays Painted', 'Food Conveyor 20trays Painted - manufactured by Densco Health Product (Food Conveyor)', 5600.00, 676, 'Food Conveyor', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791428430/densco/products/gn0lngfxazniya5m7emb.jpg', '2026-10-07 12:32:29', 0),
(73, 'Food Conveyor 20trays Stainless', 'Food Conveyor 20trays Stainless - manufactured by Densco Health Product (Food Conveyor)\r\n33 x 27 x 16.5 in', 20900.00, 23, 'Food Conveyor', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791428441/densco/products/as70m06vgmykufjk97rp.jpg', '2026-10-07 12:33:01', 0),
(74, 'Food Conveyor 30trays Stainless', 'Food Conveyor 30trays Stainless - manufactured by Densco Health Product (Food Conveyor)\r\n20 x 32 x 50 in', 41500.00, 246, 'Food Conveyor', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791428450/densco/products/rqhun66sjlpw6cjis1ak.jpg', '2026-10-07 12:33:23', 0),
(75, 'Droplight w/ out Wheels', 'Droplight w/ out  Wheels - manufactured by Densco Health Product (Lighting)', 2450.00, 89, 'Lighting', 'assets/products/product_6ac64eb621c21.jpg', '2026-10-07 12:33:45', 1),
(76, 'Droplight w/ Wheels Stainless', 'Droplight w/ Wheels Stainless - manufactured by Densco Health Product (Lighting)', 2600.00, 76, 'Lighting', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791428482/densco/products/r1cqcrqvpvgzy4yptnby.jpg', '2026-10-07 12:34:00', 0),
(77, 'Droplight w/o Wheels Stainless', 'Droplight w/o Wheels Stainless - manufactured by Densco Health Product (Lighting)', 2450.00, 42, 'Lighting', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791428508/densco/products/pkphbnqvby0win37n3x8.jpg', '2026-10-07 12:34:50', 0),
(78, 'Emergency Cart Painted', 'Painted-finish emergency cart with multiple drawers and a lower cabinet with doors; caster wheels for mobility. Manufactured by Densco Health Product.', 6800.00, 152536, 'Cart', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791428547/densco/products/xkzviz6hijkjiq1xwvtb.jpg', '2026-10-07 12:35:19', 0),
(79, 'Emergency Cart Stainless', 'Stainless steel emergency cart with multiple drawers, a lower cabinet with doors, an IV pole attachment, and a side waste bin holder; caster wheels for mobility. Manufactured by Densco Health Product.', 11500.00, 56, 'Cart', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791428559/densco/products/d9l9ez7x7uzwkbqumup0.jpg', '2026-10-07 12:35:42', 0),
(80, 'Emergency Cart w/ Cardiac Board', 'Emergency Cart w/ Cardiac Board - manufactured by Densco Health Product (Cart)', 12500.00, 152540, 'Cart', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791428571/densco/products/sdtsumgpvf58quyyb5ui.jpg', '2026-10-07 12:36:09', 0),
(81, 'ECG Table Painted', 'ECG Table Painted - manufactured by Densco Health Product (Table)', 3200.00, 967, 'Table', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791428684/densco/products/cybwkr5rsundss8vamub.jpg', '2026-10-07 12:36:25', 0),
(82, 'ECG Table Stainless', 'ECG Table Stainless - manufactured by Densco Health Product (Table)\r\n76 x 46 x 36 in', 5100.00, 33, 'Table', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791428693/densco/products/iuycaiusbvrhryqoubml.jpg', '2026-10-07 12:36:50', 0),
(83, 'Hamilton Type Painted', 'Hamilton Type Painted - manufactured by Densco Health Product (OB/Delivery Table)', 4545.00, 767, 'Diagnostics', NULL, '2026-10-07 12:37:07', 1),
(84, 'Bedpan Plastic', 'Bedpan Plastic - manufactured by Densco Health Product (Bedpan)\r\n14 x 8.5 x 2.5 in', 50.00, 13, 'Bedpan', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791428711/densco/products/msvflhdkwgt6auhbiz4z.jpg', '2026-10-07 12:37:26', 0),
(85, 'Bedside Cabinet Stainless', 'Bedside Cabinet Stainless - manufactured by Densco Health Product (Cabinet)', 1450.00, 6768, 'Hospital Bed', NULL, '2026-10-07 12:37:52', 1),
(86, 'Bedside Cabinet Painted', 'Bedside Cabinet Painted - manufactured by Densco Health Product (Cabinet)\r\n31 x 19 x 16 in', 1700.00, 342, 'Hospital Bed', NULL, '2026-10-07 12:38:34', 1),
(87, 'Bedside Table w/ Wheels Painted', 'Bedside Table w/ Wheels Painted - manufactured by Densco Health Product (Table)\r\n31 x 19 x 16 in', 2700.00, 66, 'Hospital Bed', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791428775/densco/products/ltdfkesx0akptw7fbbwm.jpg', '2026-10-07 12:38:57', 0),
(88, 'Bedside Table w/ Wheels Stainless', 'Bedside Table w/ Wheels Stainless - manufactured by Densco Health Product (Table)', 4100.00, 345, 'Hospital Bed', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791428790/densco/products/zw1j7ozokiclpqhwizbx.jpg', '2026-10-07 12:39:12', 0),
(89, 'Bedside Table w/o Wheels Painted', 'Bedside Table w/o Wheels Painted - manufactured by Densco Health Product (Table)', 2500.00, 432, 'Hospital Bed', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791428804/densco/products/unggxzobb0phuruoeqfd.jpg', '2026-10-07 12:39:42', 0),
(90, 'Bedside Table w/o Wheels Stainless', 'Bedside Table w/o Wheels Stainless - manufactured by Densco Health Product (Table)\r\n31 x 19 x 16 in', 3900.00, 67, 'Hospital Bed', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791428813/densco/products/hhrwpvr27t090nyvj7ff.jpg', '2026-10-07 12:40:10', 0),
(91, 'Chart Holder 24 Capacity Painted', 'Chart Holder 24 Capacity Painted - manufactured by Densco Health Product (Chart Holder)\r\n22 x 33.5 x 23 in', 4800.00, 45, 'Chart Holder', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791428824/densco/products/zcycjj9jyg8ogxkujxwu.jpg', '2026-10-07 12:40:35', 0),
(92, 'Chart Holder 24 Cap. Stainless', 'Chart Holder 24 Cap. Stainless - manufactured by Densco Health Product (Chart Holder)\r\n22 x 33.5 x 23 in', 7800.00, 432, 'Chart Holder', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791428834/densco/products/oaqzqnzmgswkgh0hfpwg.jpg', '2026-10-07 12:41:00', 0),
(93, 'Instrument Cabinet Painted', 'Instrument Cabinet Painted - manufactured by Densco Health Product (Cabinet)\r\n16 x 31 x 60 in', 13000.00, 565, 'Cabinet', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791428844/densco/products/ziiqiasaji00g91sjesr.jpg', '2026-10-07 12:41:20', 0),
(94, 'IV Pole Stainless', 'IV Pole Stainless - manufactured by Densco Health Product (IV Stand)', 7654.00, 45, 'Diagnostics', NULL, '2026-10-07 12:41:38', 1),
(95, 'IV Stand 2 Hooks Stainless', 'IV Stand 2 Hooks Stainless - manufactured by Densco Health Product (IV Stand)\r\n37 x 60 x 13 in', 2100.00, 235, 'IV Stand', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791428855/densco/products/nb9rm2pk2ai0jdycyqz1.jpg', '2026-10-07 12:42:00', 0),
(96, 'IV Stand 4Hooks Stainless', 'IV Stand 4Hooks Stainless - manufactured by Densco Health Product (IV Stand)', 2200.00, 443, 'IV Stand', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791428864/densco/products/jdz5ivgn0bi1b93a9btj.jpg', '2026-10-07 12:42:13', 0),
(97, 'Kick Bucket Painted', 'Kick Bucket Painted - manufactured by Densco Health Product (Bucket)\r\n13 x 10 x 11 cm', 1700.00, 11, 'Bucket', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791428873/densco/products/uv3g9fwyg5ammyjaspnx.jpg', '2026-10-07 12:42:33', 0),
(98, 'Kick Bucket Stainless', 'Kick Bucket Stainless - manufactured by Densco Health Product (Bucket)\r\n13 x 10 x 11 cm', 1800.00, 6788, 'Bucket', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791428882/densco/products/lla1csfdiiqplzizckce.jpg', '2026-10-07 12:42:59', 0),
(99, 'Linen Hamper Painted', 'Linen Hamper Painted - manufactured by Densco Health Product (Hamper)\r\n20 x 20 x 36 in', 2900.00, 44, 'Hamper', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791428891/densco/products/vquahsovo9nkmz4rz2cm.jpg', '2026-10-07 12:43:26', 0),
(100, 'Linen Hamper Stainless', 'Linen Hamper Stainless - manufactured by Densco Health Product (Hamper)', 3800.00, 48, 'Hamper', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791428901/densco/products/el6lusoegqnqlhpush3m.jpg', '2026-10-07 12:43:41', 0),
(101, 'Mayo Stand 2 Wheels Painted', 'Mayo Stand 2 Wheels Painted - manufactured by Densco Health Product (Mayo Stand)', 1400.00, 3243, 'Mayo Stand', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791428912/densco/products/lbqpnh4fbrphzi7qcmb7.jpg', '2026-10-07 12:43:56', 0),
(102, 'Anesthesia Table Painted', 'Anesthesia Table Painted - manufactured by Densco Health Product (Table) \r\n14 x 20 x 37 in', 3400.00, 55, 'Table', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791428925/densco/products/ltqryfccj7nriijwazhe.jpg', '2026-10-07 12:44:18', 0),
(103, 'Anesthesia Table Stainless', 'Anesthesia Table Stainless - manufactured by Densco Health Product (Table)', 6300.00, 3453, 'Table', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791428958/densco/products/nwr2aiwpyehkxxnmbxrn.jpg', '2026-10-07 12:44:31', 0),
(104, 'Autoclave Machine 280L', 'Autoclave Machine 280L - manufactured by Densco Health Product (Sterilizer)', 54634.00, 55, 'Diagnostics', NULL, '2026-10-07 12:44:50', 1),
(105, 'Baby Bassinet Basket', 'Baby Bassinet Basket - manufactured by Densco Health Product (Baby Bassinet)', 450.00, 2134, 'Baby Bassinet', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791428980/densco/products/voc8oxodtvykdylm9i28.jpg', '2026-10-07 12:45:04', 0),
(106, 'Baby Bassinet Foam', 'Baby Bassinet Foam - manufactured by Densco Health Product (Baby Bassinet)\r\n26.5 x 14 x 2 in', 450.00, 456, 'Baby Bassinet', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791428989/densco/products/cx3oepegyzmmoqnwmns4.jpg', '2026-10-07 12:45:46', 0),
(107, 'Baby Bassinet w/ Cabinet Sliding Door', 'Baby Bassinet w/ Cabinet Sliding Door - manufactured by Densco Health Product (Baby Bassinet)', 8000.00, 44, 'Baby Bassinet', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791428999/densco/products/pgy4qal4ayz0bt6xujen.jpg', '2026-10-07 12:46:04', 0),
(108, 'Baby Crib w/ Wheels Painted', 'Baby Crib w/ Wheels Painted - manufactured by Densco Health Product (Baby Crib)\r\n48 x 19 x 25 in', 33333.00, 56, 'Baby Crib', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791430514/densco/products/ygkx3aimwbwahwywqfg3.jpg', '2026-10-07 12:46:30', 1),
(109, 'Baby Bassinet Stand Painted', 'Baby Bassinet Stand Painted - manufactured by Densco Health Product (Baby Bassinet) \r\n35.5 x 71.1 x 20.3 cm', 1600.00, 2354, 'Baby Bassinet', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791430525/densco/products/idpsqsfbwzhaj19ei0eh.jpg', '2026-10-07 12:46:57', 0),
(110, 'Baby Bassinet Stand Stainless', 'Baby Bassinet Stand Stainless - manufactured by Densco Health Product (Baby Bassinet)', 2600.00, 34534, 'Baby Bassinet', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791430535/densco/products/sq47lbugats42kkgjxzi.jpg', '2026-10-07 12:47:14', 0),
(111, 'Chart Holder 12 Capacity Painted', 'Chart Holder 12 Capacity Painted - manufactured by Densco Health Product (Chart Holder)', 2450.00, 2345, 'Chart Holder', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791430545/densco/products/cqi3wxxs8ubuniu8dgo4.jpg', '2026-10-07 12:50:31', 0),
(112, 'Chart Holder 12 Capacity Stainless', 'Chart Holder 12 Capacity Stainless - manufactured by Densco Health Product (Chart Holder)', 3550.00, 55, 'Chart Holder', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791430553/densco/products/hwbeumpiysuss7pljwoh.jpg', '2026-10-07 12:50:45', 0),
(113, 'Circular Table Painted w/out Wheels', 'Circular Table Painted w/out Wheels - manufactured by Densco Health Product (Table)', 6900.00, 2323, 'Table', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791430565/densco/products/iq2kg6itwpbwgpd0cgll.jpg', '2026-10-07 12:50:58', 0),
(114, 'Circular Table Stainless w/out Railings', 'Circular Table Stainless w/out Railings - manufactured by Densco Health Product (Table)', 10800.00, 121, 'Table', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791430575/densco/products/ykc8gz0enwgv3ir1lj1o.jpg', '2026-10-07 12:51:12', 0),
(115, 'Dental Cabinet Stainless', 'Dental Cabinet Stainless - manufactured by Densco Health Product (Cabinet)', 19500.00, 343, 'Cabinet', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791430797/densco/products/dnrdiecfmn4qu8knlfle.jpg', '2026-10-07 12:51:30', 0),
(116, 'Dialysis Chair Stainless', 'Dialysis Chair Stainless - manufactured by Densco Health Product (Chair)', 11000.00, 213, 'Chair', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791430863/densco/products/bjdogqd1i4otgcyukdwa.jpg', '2026-10-07 12:51:44', 0),
(117, 'Dressing Carriage Painted', 'Dressing Carriage Painted - manufactured by Densco Health Product (Cart)', 5400.00, 33, 'Cart', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791430887/densco/products/ujb9ac7in67ar5mezrbs.jpg', '2026-10-07 12:51:56', 1),
(118, 'Dressing Carriage w/ Pail Painted', 'Dressing Carriage w/ Pail Painted - manufactured by Densco Health Product (Cart) \r\n30.5 x 16 x 30 in', 5900.00, 323, 'Basin/Pail', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791430980/densco/products/fohayzpeb7ouyf20wz5y.jpg', '2026-10-07 12:52:17', 0),
(122, 'Stretcher Ambulance Painted', 'Stretcher Ambulance Painted - manufactured by Densco Health Product (Stretcher / Ambulance)\r\n\r\ndimension: 16 x 71 x 22 in', 5000.00, 3, 'Stretcher / Ambulance', 'https://res.cloudinary.com/cm94cbki/image/upload/v1791422078/densco/products/zprrnu0qimsjbm2antaa.png', '2026-10-08 01:14:39', 0);

-- --------------------------------------------------------

--
-- Table structure for table `restocks`
--

CREATE TABLE `restocks` (
  `restock_id` int NOT NULL,
  `product_id` int NOT NULL,
  `supplier_name` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `quantity` int NOT NULL,
  `restock_date` date NOT NULL,
  `notes` text COLLATE utf8mb4_general_ci,
  `staff_id` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `restocks`
--

INSERT INTO `restocks` (`restock_id`, `product_id`, `supplier_name`, `quantity`, `restock_date`, `notes`, `staff_id`, `created_at`) VALUES
(1, 102, 'dsasdsad', 10, '2026-10-08', 'dsadsadsd', 2, '2026-10-07 17:14:56'),
(2, 100, 'nafdsssddscsdc', 5, '2026-10-08', 'dvcsda', 3, '2026-10-08 09:57:00');

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
(3, 'Densco', 'Inventory', 'inventory@densco.com', '$2y$10$AY59dKmLk4CVMsufNlCFGOYbfaFQvvMRSqh7FnhuZ9QaJCuHyRHl2', 'inventory', '2026-10-07 09:53:21'),
(5, 'Mark Ryan', 'Pardilla', 'mark@gmail.com', '$2y$10$Z60BRyk0FVXoHkzj5K8zbOcxQrkHfBrIAYg9xcp09.cRkU0T9eZC2', 'admin', '2026-10-08 01:37:35');

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
(2, 'Mark Ryan Pardilla', 'markpardilla66@gmail.com', '$2y$10$i9ncw8eJPZFc1CGp8nP/DeZ8hVXizv8qtAznArV5HfPK2LJtmcn82', NULL, NULL, '2026-10-07 08:56:49'),
(3, 'Cabaluna Adrian Marie', 'cabalunaahyan@gmail.com', '$2y$10$YWlLYv8rYvYDSkiDy8UaLu70shqvKd1ocCi1Zf3SqCylpQFw7628q', NULL, NULL, '2026-10-08 05:13:45');

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
-- Indexes for table `restocks`
--
ALTER TABLE `restocks`
  ADD PRIMARY KEY (`restock_id`),
  ADD KEY `product_id` (`product_id`);

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
  MODIFY `order_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `order_item_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `product_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=125;

--
-- AUTO_INCREMENT for table `restocks`
--
ALTER TABLE `restocks`
  MODIFY `restock_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `staff`
--
ALTER TABLE `staff`
  MODIFY `staff_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

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
