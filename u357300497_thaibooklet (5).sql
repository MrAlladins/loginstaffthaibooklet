-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: May 01, 2025 at 10:30 AM
-- Server version: 10.11.10-MariaDB
-- PHP Version: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u357300497_thaibooklet`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`u357300497_klickjonas`@`127.0.0.1` PROCEDURE `GenerateCouponCodes` ()   BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE coupon_id INT;
    DECLARE chars VARCHAR(30) DEFAULT 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    DECLARE i INT;
    DECLARE coupon_cursor CURSOR FOR SELECT id FROM coupons WHERE coupon_code IS NULL OR coupon_code = '';
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    OPEN coupon_cursor;
    
    update_loop: LOOP
        FETCH coupon_cursor INTO coupon_id;
        IF done THEN
            LEAVE update_loop;
        END IF;
        
        SET @code = '';
        SET i = 0;
        
        WHILE i < 10 DO
            SET @code = CONCAT(@code, SUBSTRING(chars, FLOOR(1 + RAND() * LENGTH(chars)), 1));
            SET i = i + 1;
        END WHILE;
        
        -- Kontrollera att koden är unik och uppdatera
        WHILE EXISTS (SELECT 1 FROM coupons WHERE coupon_code = @code) DO
            SET @code = '';
            SET i = 0;
            WHILE i < 10 DO
                SET @code = CONCAT(@code, SUBSTRING(chars, FLOOR(1 + RAND() * LENGTH(chars)), 1));
                SET i = i + 1;
            END WHILE;
        END WHILE;
        
        UPDATE coupons SET coupon_code = @code WHERE id = coupon_id;
    END LOOP;
    
    CLOSE coupon_cursor;
END$$

CREATE DEFINER=`u357300497_klickjonas`@`127.0.0.1` PROCEDURE `InsertTestRedemptions` ()   BEGIN
  DECLARE i INT DEFAULT 0;
  DECLARE coupon_count INT;
  DECLARE user_count INT;
  DECLARE random_date DATETIME;
  DECLARE random_coupon_id INT;
  DECLARE random_user_id INT;
  DECLARE verification VARCHAR(6);
  
  -- Hämta antal kuponger och användare
  SELECT COUNT(*) INTO coupon_count FROM coupons WHERE company_id = 1;
  SELECT COUNT(*) INTO user_count FROM users WHERE active = 1;
  
  -- Om det finns kuponger och användare, lägg till testdata
  IF coupon_count > 0 AND user_count > 0 THEN
    -- Skapa 150 slumpmässiga inlösningar
    WHILE i < 150 DO
      -- Slumpmässigt datum inom de senaste 30 dagarna
      SET random_date = DATE_SUB(NOW(), INTERVAL FLOOR(RAND() * 30) DAY);
      -- Lägg till klockan
      SET random_date = DATE_ADD(random_date, INTERVAL FLOOR(RAND() * 24) HOUR);
      SET random_date = DATE_ADD(random_date, INTERVAL FLOOR(RAND() * 60) MINUTE);
      
      -- Slumpmässig kupong från företag 1
      SELECT id INTO random_coupon_id FROM coupons WHERE company_id = 1 ORDER BY RAND() LIMIT 1;
      
      -- Slumpmässig användare
      SELECT id INTO random_user_id FROM users WHERE active = 1 ORDER BY RAND() LIMIT 1;
      
      -- Skapa verifieringskod
      SET verification = CONCAT(
        CHAR(65 + FLOOR(RAND() * 26)),
        CHAR(65 + FLOOR(RAND() * 26)),
        CHAR(48 + FLOOR(RAND() * 10)),
        CHAR(48 + FLOOR(RAND() * 10)),
        CHAR(65 + FLOOR(RAND() * 26)),
        CHAR(65 + FLOOR(RAND() * 26))
      );
      
      -- Lägg till i coupon_uses med shop_id = 1 och slumpmässig user_id
      INSERT INTO coupon_uses (coupon_id, user_id, used_at, verification_code, shop_id)
      VALUES (random_coupon_id, random_user_id, random_date, verification, 1);
      
      -- Uppdatera current_uses i coupons-tabellen
      UPDATE coupons SET current_uses = current_uses + 1 WHERE id = random_coupon_id;
      
      SET i = i + 1;
    END WHILE;
    
    SELECT CONCAT('Added ', i, ' test redemptions');
  ELSE
    SELECT 'Missing required data. Make sure you have coupons with company_id = 1 and active users.';
  END IF;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `areas`
--

CREATE TABLE `areas` (
  `id` int(11) NOT NULL,
  `country_id` int(11) DEFAULT 1,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `areas`
--

INSERT INTO `areas` (`id`, `country_id`, `name`, `description`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 1, 'Phuket', 'Phuket area', 10, '2025-04-23 16:08:37', '2025-04-23 18:31:03'),
(2, 1, 'Pattaya', 'Pattaya area', 20, '2025-04-23 16:08:37', '2025-04-23 18:31:03'),
(3, 1, 'Bangkok', 'Bangkok area', 30, '2025-04-23 17:13:22', '2025-04-23 17:13:22'),
(4, 1, 'Chiang Mai', 'Chiang Mai area', 40, '2025-04-23 17:13:22', '2025-04-23 17:13:22'),
(5, 1, 'Hua-Hin', 'Hua Hin area', 50, '2025-04-23 18:21:35', '2025-04-23 18:31:50');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `description`, `active`) VALUES
(1, 'Restaurant', 'Restauranger och matställen', 1),
(2, 'Massage', 'Massage och spa', 1),
(3, 'Beer Bar', 'Barer och pubar', 1),
(4, 'Shopping', 'Butiker och köpcentrum', 1),
(5, 'Activities', 'Aktiviteter och upplevelser', 1),
(6, 'Sport', 'Sport activities', 1);

-- --------------------------------------------------------

--
-- Table structure for table `companies`
--

CREATE TABLE `companies` (
  `id` int(11) NOT NULL,
  `area` varchar(30) NOT NULL,
  `name` varchar(255) NOT NULL,
  `contact_info` text DEFAULT NULL,
  `logo_url` varchar(255) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `shop_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `address` text DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `tripadvisor` varchar(255) DEFAULT NULL,
  `keywords` varchar(255) DEFAULT NULL,
  `languages` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `companies`
--

INSERT INTO `companies` (`id`, `area`, `name`, `contact_info`, `logo_url`, `active`, `shop_id`, `description`, `address`, `latitude`, `longitude`, `tripadvisor`, `keywords`, `languages`, `email`, `phone`, `website`, `category`) VALUES
(1, 'Phuket', 'Nvidia Restaurant', 'Test kontaktinfo 1745256001', '/thaibooklet/uploads/companies/company_1745970942_681166fe80ca3.jpg', 1, NULL, '', '', NULL, NULL, 'https://www.tripadvisor.com/TEST_DIRECT_1745256001', NULL, NULL, '', '', '', ''),
(2, 'Phuket', 'EAT. bar & grill ', 'Adress med long och lat måste fixas', 'https://images.squarespace-cdn.com/content/v1/57b2d398b8a79bb69ffe717c/1476864267674-USRP714TEJSEMM8LETHP/EAT.gif?format=1500w', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(3, 'Pattaya', 'I love this Bar', 'The Bar with the warm Heart and the cold Beers.\nRestaurant with Thai, European and US-kitchen. In house dining or for take-away. Two full size pooltables, competition dart board, confotable sofas for chill, a mix of rock, pop and Thai music.', '/thaibooklet/uploads/companies/lovebar.jpg', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(7, 'Phuket', 'Butterfly Bar 1', '🦋 Welcome to The New Butterfly Bar – Patong’s Only Country Vibe! 🤠🎶\r\n\r\nSaddle up and step into Patong’s wildest Western-style saloon – where boots meet beats and every night feels like a Southern celebration!\r\n\r\nAt The New Butterfly Bar, we bring the charm of the countryside to the heart of Bangla Road with:\r\n\r\n🎵 Live country & rock tunes\r\n🥃 Ice-cold whiskey & cowboy cocktails\r\n💃 Boot-stompin’ dancing & friendly bar ladies\r\n🌵 Rustic vibes and good ol’ Western hospitality\r\n\r\nWhether you’re a honky-tonk hero or just here to two-step the night away, this is your home on the range.\r\n\r\nYeehaw, partner – the party starts at Butterfly!', '/thaibooklet/uploads/companies/butterflybar.png', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(31, 'Phuket', 'Mika Padel Karon', 'This is a testcompany ', '/thaibooklet/uploads/companies/test.jpg', 1, NULL, NULL, NULL, 0.0000000, 0.0000000, '', NULL, NULL, NULL, NULL, NULL, NULL),
(33, 'Phuket', 'padeltruck', '', '/thaibooklet/uploads/companies/8115b49bbd9b.jpg', 1, NULL, 'asdf', '', NULL, NULL, '', NULL, NULL, '', '', '', 'Sport'),
(34, 'Phuket', 'PaDaELtrucken', '+66005', '/thaibooklet/uploads/companies/company_1745970942_681166fe80ca3.jpg', 1, NULL, 'sport', 'asdf', NULL, NULL, '', NULL, NULL, 'mikahei1970@gmail.com', '086656650', '', 'Sport');

-- --------------------------------------------------------

--
-- Table structure for table `company_languages`
--

CREATE TABLE `company_languages` (
  `company_id` int(11) NOT NULL,
  `language_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `countries`
--

CREATE TABLE `countries` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `sort_order` int(11) DEFAULT 10,
  `status` enum('active','inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `countries`
--

INSERT INTO `countries` (`id`, `name`, `description`, `sort_order`, `status`) VALUES
(1, 'Thailand', 'The Land of Smiles', 10, 'active');

-- --------------------------------------------------------

--
-- Table structure for table `coupons`
--

CREATE TABLE `coupons` (
  `id` int(11) NOT NULL,
  `edition_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `value` varchar(255) NOT NULL,
  `terms` text DEFAULT NULL,
  `valid_from` date DEFAULT NULL,
  `valid_until` date DEFAULT NULL,
  `max_uses` int(11) DEFAULT NULL,
  `current_uses` int(11) NOT NULL DEFAULT 0,
  `status` enum('active','inactive','expired') NOT NULL DEFAULT 'active',
  `image_path` varchar(255) DEFAULT NULL,
  `coupon_code` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `coupons`
--

INSERT INTO `coupons` (`id`, `edition_id`, `company_id`, `category_id`, `title`, `description`, `value`, `terms`, `valid_from`, `valid_until`, `max_uses`, `current_uses`, `status`, `image_path`, `coupon_code`) VALUES
(9, 1, 1, 1, 'Get 20% off your meal', 'Discover a cozy slice of Sweden in the heart of the city. From classic Swedish meatballs to modern seasonal dishes, Karlssons Restaurang is your go-to for delicious comfort food and warm hospitality. Bring this coupon and enjoy 20% off your total bill – it’s our way of saying välkommen!', 'Get 20% off your meal with this coupon!', 'one time use', '2025-04-18', '2025-05-30', 3, 0, 'active', 'uploads/coupons/19ebb37a6e5a59a8_1744999796.jpg', '6IBG0U'),
(10, 1, 1, 1, '2-for-1 on lunch specials!', 'Perfect for a lunch date or catching up with a friend.\r\nCome hungry. Leave happy.', '2-for-1 on lunch specials!', '', '2025-04-18', '2025-05-31', 2, 1, 'active', 'uploads/coupons/7ef20e303791af4b_1744999878.jpg', 'JDVBEE'),
(11, 1, 1, 1, 'Bring the whole family to Karlssons!', 'Kids eat FREE with every adult meal – just show this coupon.\r\n🍝 Tasty meals, a relaxed atmosphere, and something for everyone.\r\n\r\nLet us take care of dinner tonight.', 'Kids eat FREE', '', '2025-04-18', '2025-05-23', 2, 0, 'active', 'uploads/coupons/afaf7a290e11f487_1744999970.jpg', 'MAZRKC'),
(12, 2, 3, 3, 'ilovemybar – Cheers to Your First Round!', 'Show this coupon and get 1 FREE beer when you buy your first one.\r\nBecause nothing brings people together like good beer – and we’re happy to pour the first one on us.\r\n\r\n🎟 Valid Monday–Thursday\r\n📍 Only at ilovemybar\r\n⏳ Offer valid until [insert date]', 'ilovemybar – Cheers to Your First Round!', 'Must be 18+ to redeem. One per person. Good vibes mandatory.', '2025-04-18', '2025-08-20', 5, 0, 'active', 'uploads/coupons/6f1fa685b7f84eb9_1745001914.png', 'K6H9CA'),
(13, 5, 7, 3, 'Welcome offer', '💥 ThaiBooklet Special Offer:\r\nShow your ThaiBooklet and get Buy 1 Get 1 Free on all cocktails from 7–9 PM – every night!\r\n\r\nWhether you\'re here to chill with friends, enjoy the vibrant nightlife, or make new memories, Butterly Bar is your go-to spot on Bangla Road. Great prices, amazing staff, and a warm welcome await!\r\n\r\n📍 Located just steps from Bangla Road – follow the butterflies!\r\n🎶 Live DJs | 🍹 Ice-cold cocktails | 🦋 Unique atmosphere\r\n\r\nButterly Bar – Where the night begins.', 'Buy 1 Get 1 Free on all cocktails from 7–9 PM', 'Only between 7-9 PM', '2025-04-20', '2026-03-11', 5, 0, 'active', 'uploads/coupons/833c92f1a3434e19_1745186185.jpg', 'MFRIIA'),
(14, 2, 3, 3, 'Free Food Friday', 'Looking for the best deal in Jomtien?\r\nAt ILoveThisBar, we’ve got cold drinks, hot vibes – and FREE food every Friday!\r\n\r\nAnd guess what?\r\nShow your ThaiBooklet.com coupon and get an extra drink on the house 🍹\r\n\r\n✅ Free Friday food\r\n✅ Bonus drink with ThaiBooklet\r\n✅ Happy hour deals\r\n✅ Good music, great people, real fun\r\n\r\nBring your friends, show your booklet, and fall in love with the bar everyone’s talking about.\r\nBecause let’s be real... I Love This Bar!', 'FFF FridayFreeFood', 'Only on fridays, and show coupon', '2025-04-20', '2026-05-20', 10, 0, 'active', 'uploads/coupons/a2d74f71c7877ea9_1745187447.jpg', '33NJ05'),
(15, 1, 31, 6, 'Try padel', 'play 2, pay 1', '50%', 'only 5 times', '2025-04-29', '2025-05-29', 5, 1, 'active', 'uploads/coupons/89c1087816039d48_1745916908.jpg', 'WZWD9N'),
(17, 1, 31, 6, 'Dinner', 'Dinnerfor 2 at partner', '50%', 'one time only', '2025-04-29', '2025-05-29', 4, 1, 'active', 'uploads/coupons/41d1f494e51f2d76_1745917085.jpg', 'YBK46U'),
(18, 1, 34, 5, 'Try padel', 'Enjoy a free dessert with any main course purchase. One free dessert per table.', '50%', 'dsfg', '2025-04-30', NULL, 1, 0, 'active', 'uploads/coupons/coupon_1746000717_8781.jpg', 'SKSN4TX2DC');

-- --------------------------------------------------------

--
-- Table structure for table `coupon_uses`
--

CREATE TABLE `coupon_uses` (
  `id` int(11) NOT NULL,
  `coupon_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `used_at` datetime NOT NULL DEFAULT current_timestamp(),
  `verification_code` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `shop_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `coupon_uses`
--

INSERT INTO `coupon_uses` (`id`, `coupon_id`, `user_id`, `used_at`, `verification_code`, `notes`, `shop_id`) VALUES
(167, 10, NULL, '2025-04-18 18:41:30', 'C3E8EA', '', 1),
(168, 17, NULL, '2025-04-29 10:04:11', '3480EC', '', 2),
(169, 15, NULL, '2025-04-29 10:04:40', 'A57064', '', 2);

-- --------------------------------------------------------

--
-- Table structure for table `editions`
--

CREATE TABLE `editions` (
  `id` int(11) NOT NULL,
  `area` varchar(30) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `published_at` datetime DEFAULT NULL,
  `status` enum('draft','published','archived') NOT NULL DEFAULT 'draft',
  `woocommerce_product_id` int(11) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `valid_until` date DEFAULT NULL,
  `area_id` int(11) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `editions`
--

INSERT INTO `editions` (`id`, `area`, `title`, `description`, `created_at`, `published_at`, `status`, `woocommerce_product_id`, `image_path`, `valid_until`, `area_id`, `sort_order`) VALUES
(1, '1', 'Karon Booklet', 'Discover Khanom Like a Local – With Thaibooklet! 🌴\r\n\r\nReady to explore the hidden gems of Khanom while saving money? Thaibooklet is your ultimate guide to exclusive deals and unforgettable experiences in the area. Whether you\'re craving authentic Thai cuisine, dreaming of relaxing spa days, or planning exciting adventures – Thaibooklet connects you to the best local offers.\r\n\r\nWith carefully selected discounts and insider tips, Thaibooklet helps you make the most of your stay while supporting local businesses. It’s more than just a booklet – it’s your personal key to discovering the heart of Khanom.\r\n\r\nWhy get Thaibooklet?\r\n✅ Handpicked deals from top local spots\r\n✅ Save money while exploring more\r\n✅ Support small businesses and the local community\r\n✅ Perfect for travelers, expats, and locals alike!\r\n\r\nDon\'t miss out on the experiences that make Khanom truly special. Grab your Thaibooklet and start unlocking the best of Khanom today!', '2025-04-16 10:03:33', '2025-04-15 13:23:04', 'published', NULL, 'uploads/editions/Booklet_600x600_PatongKata20241.png', '2025-12-31', 1, 10),
(2, '2', 'Pattaya Booklet', 'Experience more, spend less – that’s what Thaibooklet Pattaya is all about! Whether you\'re a first-time visitor or a long-time local, this booklet is packed with exclusive discounts and offers at the city\'s best restaurants, spas, beach clubs, bars, tours, and much more.\r\n\r\nPattaya is full of energy, flavor, and adventure – and with Thaibooklet, you’ll discover it all while saving big. From hidden gems to popular hotspots, our handpicked deals make it easy (and affordable) to enjoy everything this vibrant city has to offer.\r\n\r\nWhy grab your Thaibooklet in Pattaya?\r\n✅ Huge savings at trusted local businesses\r\n✅ Discover new places and experiences\r\n✅ Ideal for tourists, digital nomads, and expats\r\n✅ Support local entrepreneurs while enjoying more for less!\r\n\r\nDon\'t just visit Pattaya – experience it fully. Thaibooklet is your pocket-sized VIP pass to the best of the city.\r\n\r\n', '2025-04-18 08:39:41', '2025-04-18 11:02:08', 'published', NULL, 'uploads/editions/pattaya.jpg', '2025-12-31', 2, 20),
(5, '1', 'Party Phuket', '🎉 Discover the Party Side of Thailand with ThaiBooklet! 🇹🇭🍹\r\n\r\nReady to turn your Thai vacation into a non-stop celebration? ThaiBooklet is your ultimate VIP pass to the hottest parties, wildest bars, and exclusive deals in town!\r\n\r\nWhether you\'re in Phuket, Pattaya, or Bangkok, ThaiBooklet gives you insider access to:\r\n\r\n✅ Free shots & 2-for-1 cocktails at top-rated bars\r\n✅ Discounts on boat parties, beach clubs & nightlife tours\r\n✅ Entry to private events and wild pool parties\r\n✅ Special offers on tattoos, scooters, and hangover cures 😉\r\n\r\nNo apps. No hassle. Just flash your ThaiBooklet and unlock the good times.\r\n\r\nPerfect for:\r\n🎊 Party crews\r\n🍺 Solo explorers\r\n💃 Bachelor(ette) squads\r\n🌴 Festival-goers\r\n\r\nYour trip deserves legendary nights – make it happen with ThaiBooklet!\r\nFollow the QR code. Grab the booklet. Let the party begin.', '2025-04-20 20:45:45', '2025-04-20 00:00:00', 'published', NULL, 'uploads/editions/pp.jpg', '2025-12-31', 1, 30),
(6, '3', 'Massage & Spa', 'Welcome to a peaceful oasis where your well-being is our priority. We offer professional treatments including traditional Thai massage, oil massage, foot massage, and soothing spa packages.\r\n\r\nLet our experienced staff take care of you with warmth and care. With us, you’ll enjoy:\r\n\r\n✅ Deep tissue massage to relieve tension\r\n✅ Relaxing spa experiences for inner peace\r\n✅ Fresh, calming atmosphere with natural scents\r\n✅ A moment just for you – or with someone you love', '2025-04-20 22:01:04', '2025-04-21 00:05:56', 'draft', NULL, 'uploads/editions/bbd366e70f5227b4_1745186464.jpg', NULL, 1, 40),
(7, '', 'Pattaya Golfer', '⛳ Pattaya – Golf & Vibrancy Combined\r\n\"Tee off in Pattaya, where world-class golf meets thrilling nightlife and golden beaches. With over 20 top-tier courses just a short drive from the city, Pattaya is the perfect destination for golfers seeking both challenge and excitement. Play by day, relax or party by night – it’s the ultimate golf getaway!\"', '2025-04-23 18:19:24', '2025-04-23 20:20:28', 'published', NULL, 'uploads/editions/83e2a236cd575c2e_1745432364.jpg', NULL, 2, 20),
(8, '', 'Phuket Golfer', '🏝 Phuket – Golf in Paradise\r\n\"Discover the magic of golf in Phuket – lush tropical scenery, ocean views, and beautifully designed courses. Whether you\'re a seasoned pro or a casual player, Phuket offers unforgettable rounds with luxury resorts and pristine beaches just minutes away. It’s more than golf – it’s paradise.\"', '2025-04-23 18:19:55', '2025-04-23 20:20:33', 'published', NULL, 'uploads/editions/5f868d40522bb24b_1745432395.jpg', NULL, 1, 10),
(9, '', 'Hua Hin Golfer', '🌅 Hua Hin – Where Tradition Meets the Fairway\r\n\"Step into a calmer rhythm in Hua Hin, Thailand’s original beach resort town. Known for its royal charm and laid-back atmosphere, Hua Hin boasts some of the country’s most scenic and playable golf courses. Enjoy peaceful fairways, stunning coastal views, and a relaxed vibe perfect for a golfer’s retreat.\"', '2025-04-23 18:20:17', '2025-04-23 20:20:38', 'draft', NULL, 'uploads/editions/bf594b8e74458abb_1745432417.jpg', NULL, 5, 50);

-- --------------------------------------------------------

--
-- Table structure for table `languages`
--

CREATE TABLE `languages` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `code` varchar(10) NOT NULL,
  `active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `languages`
--

INSERT INTO `languages` (`id`, `name`, `code`, `active`) VALUES
(1, 'Thai', 'th', 1),
(2, 'English', 'en', 1),
(3, 'Swedish', 'sv', 1),
(4, 'Finnish', 'fi', 1),
(5, 'Chinese', 'zh', 1),
(6, 'Japanese', 'ja', 1),
(7, 'German', 'de', 1),
(8, 'French', 'fr', 1),
(9, 'Russian', 'ru', 1),
(10, 'Thai', 'th', 1),
(11, 'English', 'en', 1),
(12, 'Swedish', 'sv', 1),
(13, 'Finnish', 'fi', 1),
(14, 'Norwegian', 'no', 1),
(15, 'Danish', 'da', 1),
(16, 'German', 'de', 1),
(17, 'French', 'fr', 1),
(18, 'Spanish', 'es', 1),
(19, 'Italian', 'it', 1),
(20, 'Russian', 'ru', 1),
(21, 'Chinese', 'zh', 1),
(22, 'Japanese', 'ja', 1),
(23, 'Korean', 'ko', 1),
(24, 'Arabic', 'ar', 1);

-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `role_id` int(11) NOT NULL,
  `permission` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `role_permissions`
--

INSERT INTO `role_permissions` (`role_id`, `permission`) VALUES
(1, 'manage_staff'),
(1, 'redeem_coupon'),
(1, 'view_statistics'),
(4, 'redeem_coupon');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(255) NOT NULL,
  `setting_value` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `shops`
--

CREATE TABLE `shops` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `contact_info` text DEFAULT NULL,
  `company_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `shops`
--

INSERT INTO `shops` (`id`, `name`, `address`, `contact_info`, `company_id`, `created_at`) VALUES
(1, 'Karlssons Restaurant Shop', NULL, NULL, 1, '2025-04-17 14:50:22'),
(3, 'Mika Padel Karon Butik', NULL, NULL, 31, '2025-04-29 16:15:40'),
(4, 'EAT. bar & grill  Shop', NULL, NULL, 2, '2025-04-29 22:48:11'),
(5, 'Butterfly Bar 1 Shop', NULL, NULL, 7, '2025-04-30 07:34:28'),
(6, 'PaDaELtrucken Shop', NULL, NULL, 34, '2025-04-30 07:41:35'),
(7, 'padeltruck Shop', NULL, NULL, 33, '2025-04-30 20:22:38');

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

CREATE TABLE `staff` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `shop_id` int(11) NOT NULL,
  `role` varchar(50) DEFAULT 'staff',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `username` varchar(50) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `staff`
--

INSERT INTO `staff` (`id`, `user_id`, `shop_id`, `role`, `created_at`, `username`, `password`, `email`, `active`) VALUES
(10, 19, 1, '4', '2025-04-18 16:53:09', NULL, '', NULL, 1),
(11, 20, 1, '1', '2025-04-19 11:25:36', NULL, '', NULL, 1),
(14, 25, 3, '1', '2025-04-29 16:15:40', NULL, '', NULL, 1),
(19, 34, 3, '1', '2025-04-29 16:42:40', NULL, '$2y$10$6ggUMk1KPW0J9.QAwLNk3.YD1WnMd.ZrgDbfY36kUDxNKBHrAgVwC', NULL, 1),
(20, 35, 3, '1', '2025-04-29 16:48:02', NULL, '', NULL, 1),
(21, 10, 6, '1', '2025-04-30 07:42:09', NULL, '', NULL, 1),
(22, 36, 6, '1', '2025-04-30 08:33:53', NULL, '', NULL, 1),
(23, 36, 6, 'staff', '2025-04-30 10:19:34', 'bbkk', '$2y$10$XGEgC2rU/mAW3T8BzI3Z8.tPdARmyGKMYyqQvRjDkYGQkJzcPCWGC', 'bkk@dev.com', 1),
(24, 36, 6, 'staff', '2025-04-30 15:12:51', 'newstaff', '123', 'newstaff@dev.com', 1),
(25, 37, 3, '1', '2025-04-30 20:42:54', NULL, '', NULL, 1),
(26, 37, 3, 'staff', '2025-04-30 20:55:34', 'Thanaphat', '123', 'thanaphat@sweden.se', 1);

-- --------------------------------------------------------

--
-- Table structure for table `staff_auth`
--

CREATE TABLE `staff_auth` (
  `staff_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `last_login` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_roles`
--

CREATE TABLE `staff_roles` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `staff_roles`
--

INSERT INTO `staff_roles` (`id`, `name`, `description`) VALUES
(1, 'Admin', 'Can manage staff and view statistics for a specific shop'),
(4, 'Cashier', 'Can Scan coupons');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `role` enum('admin','manager','customer','systemadmin') DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `password`, `name`, `created_at`, `role`, `active`) VALUES
(10, 'mikahei1970@gmail.com', '$2y$10$aRnj6A4H.8a1iouJ/P3ahe5j0iduatcj3SYCY0kER3DV4nThdmhzm', 'Mika', '2025-04-16 11:20:05', 'admin', 1),
(11, 'jonas.d.stromberg@gmail.com', '$2y$10$4kbAegnBH9kOKTADrsxdDOZkJ02E84vS00CxpYpojXf30wuCRySQq', 'jag', '2025-04-16 12:21:37', 'systemadmin', 1),
(12, 'mikaheik1970@gmail.com', '$2y$10$LPRCgNMKDJ9wU4Uf2vt1V.rZlwuCcaRLPy4BJLbnU3Y8F9AGopxva', 'Mika test', '2025-04-16 12:39:46', 'manager', 1),
(19, 'bkkarrow@gmail.com', '$2y$10$TK1euGrV1OGRqNlyvQC.heDUIGcD3huelmeHvBYQY9JYNnZtKZbgC', 'bkkarrow@gmail.com', '2025-04-18 16:53:09', 'customer', 1),
(20, 'mgm@mgm.com', '$2y$10$M8sZd7jh6YEu9RrL/6cnYefzR8m0dFZxWONxnOzcVV5oG6JzBXI7K', 'mgm@mgm.com', '2025-04-19 11:25:36', 'customer', 1),
(23, 'mika@com7.se', '$2y$10$SLemzhWU4PYn6E/Bxawvye/9gPSDt.cLPfsLBjPNsT.0i7q.X2WGK', 'Mika Heikkinen', '2025-04-28 09:19:48', 'customer', 1),
(24, 'mikpa@thaibooklet.local', '$2y$10$XmhTzBSn75dem2IlsFRf3O/fJ7q3KE7M4bK5cSrFZcyIVf6rN1Aa6', 'mikpa', '2025-04-29 09:46:45', 'customer', 1),
(25, 'admin@mikapadel.com', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy', 'Mika Padel Admin', '2025-04-29 16:15:40', 'admin', 1),
(30, 'new.admin@mikapadel.com', '$2y$10$CebJ5YJnDT.IgFgFzvPh9utHp.riFKe2Sj1HNeBNZfGJchxYGbNdi', 'New Admin User', '2025-04-29 16:33:23', 'admin', 1),
(34, 'shop.admin@mikapadel.com', '$2y$10$6ggUMk1KPW0J9.QAwLNk3.YD1WnMd.ZrgDbfY36kUDxNKBHrAgVwC', 'Shop Admin', '2025-04-29 16:42:40', 'admin', 1),
(35, 'exact.copy@mikapadel.com', '$2y$10$M8sZd7jh6YEu9RrL/6cnYefzR8m0dFZxWONxnOzcVV5oG6JzBXI7K', 'mgm@mgm.com', '2025-04-29 16:48:02', 'customer', 1),
(36, 'juthamas.chimmee@gmail.com', '$2y$10$9SlTb0GYaKtgmYlkxAX1q.yu9SG4q1M23WpTaW0yIhv0Mk9JyYoFC', 'Jonas', '2025-04-30 08:33:53', 'admin', 1),
(37, 'bkkdevelop@gmail.com', '$2y$10$zZlqB2a95gl.vxkpLwoYEOudc28JSbLkq2aPZAjNzJGaFLQu70lDC', 'bkk', '2025-04-30 20:42:54', 'admin', 1);

-- --------------------------------------------------------

--
-- Table structure for table `user_editions`
--

CREATE TABLE `user_editions` (
  `user_id` int(11) NOT NULL,
  `edition_id` int(11) NOT NULL,
  `purchased_at` datetime NOT NULL DEFAULT current_timestamp(),
  `woocommerce_order_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_editions`
--

INSERT INTO `user_editions` (`user_id`, `edition_id`, `purchased_at`, `woocommerce_order_id`) VALUES
(10, 1, '2025-04-27 20:04:48', 1702),
(23, 1, '2025-04-28 09:19:49', 1703);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `areas`
--
ALTER TABLE `areas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_area_country` (`country_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `companies`
--
ALTER TABLE `companies`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `company_languages`
--
ALTER TABLE `company_languages`
  ADD PRIMARY KEY (`company_id`,`language_id`),
  ADD KEY `language_id` (`language_id`);

--
-- Indexes for table `countries`
--
ALTER TABLE `countries`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `coupons`
--
ALTER TABLE `coupons`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `coupon_code` (`coupon_code`),
  ADD KEY `edition_id` (`edition_id`),
  ADD KEY `company_id` (`company_id`),
  ADD KEY `coupons_ibfk_3` (`category_id`);

--
-- Indexes for table `coupon_uses`
--
ALTER TABLE `coupon_uses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `coupon_id` (`coupon_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `editions`
--
ALTER TABLE `editions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_edition_area` (`area_id`);

--
-- Indexes for table `languages`
--
ALTER TABLE `languages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`role_id`,`permission`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `shops`
--
ALTER TABLE `shops`
  ADD PRIMARY KEY (`id`),
  ADD KEY `company_id` (`company_id`);

--
-- Indexes for table `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `shop_id` (`shop_id`);

--
-- Indexes for table `staff_auth`
--
ALTER TABLE `staff_auth`
  ADD PRIMARY KEY (`staff_id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `staff_roles`
--
ALTER TABLE `staff_roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_editions`
--
ALTER TABLE `user_editions`
  ADD PRIMARY KEY (`user_id`,`edition_id`),
  ADD KEY `edition_id` (`edition_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `areas`
--
ALTER TABLE `areas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `companies`
--
ALTER TABLE `companies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `countries`
--
ALTER TABLE `countries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `coupons`
--
ALTER TABLE `coupons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `coupon_uses`
--
ALTER TABLE `coupon_uses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=170;

--
-- AUTO_INCREMENT for table `editions`
--
ALTER TABLE `editions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `languages`
--
ALTER TABLE `languages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `shops`
--
ALTER TABLE `shops`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `staff`
--
ALTER TABLE `staff`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `staff_roles`
--
ALTER TABLE `staff_roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `areas`
--
ALTER TABLE `areas`
  ADD CONSTRAINT `fk_area_country` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`);

--
-- Constraints for table `company_languages`
--
ALTER TABLE `company_languages`
  ADD CONSTRAINT `company_languages_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `company_languages_ibfk_2` FOREIGN KEY (`language_id`) REFERENCES `languages` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `coupons`
--
ALTER TABLE `coupons`
  ADD CONSTRAINT `coupons_ibfk_1` FOREIGN KEY (`edition_id`) REFERENCES `editions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `coupons_ibfk_2` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `coupons_ibfk_3` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `coupon_uses`
--
ALTER TABLE `coupon_uses`
  ADD CONSTRAINT `coupon_uses_ibfk_1` FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `coupon_uses_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `editions`
--
ALTER TABLE `editions`
  ADD CONSTRAINT `fk_edition_area` FOREIGN KEY (`area_id`) REFERENCES `areas` (`id`);

--
-- Constraints for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD CONSTRAINT `role_permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `staff_roles` (`id`);

--
-- Constraints for table `shops`
--
ALTER TABLE `shops`
  ADD CONSTRAINT `shops_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`);

--
-- Constraints for table `staff`
--
ALTER TABLE `staff`
  ADD CONSTRAINT `staff_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `staff_ibfk_2` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`);

--
-- Constraints for table `staff_auth`
--
ALTER TABLE `staff_auth`
  ADD CONSTRAINT `staff_auth_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_editions`
--
ALTER TABLE `user_editions`
  ADD CONSTRAINT `user_editions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_editions_ibfk_2` FOREIGN KEY (`edition_id`) REFERENCES `editions` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
