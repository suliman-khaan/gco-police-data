<?php
/**
 * Seed data: the 43 territorial police forces of England and Wales.
 * Source: the Forces sheet of GCO-Police-FOI-Data-Template.xlsx.
 * 'aliases' is a comma-separated list used for import matching and live search;
 * the force name, its slug-derived name and the NPCC short name are matched automatically.
 *
 * @package GCO_Police_Data
 */

defined( 'ABSPATH' ) || exit;

return array(
	array( 'slug' => 'avon-and-somerset', 'name' => 'Avon and Somerset Police', 'country' => 'England', 'aliases' => 'Avon & Somerset,Avon and Somerset' ),
	array( 'slug' => 'bedfordshire', 'name' => 'Bedfordshire Police', 'country' => 'England', 'aliases' => 'Bedfordshire' ),
	array( 'slug' => 'cambridgeshire', 'name' => 'Cambridgeshire Constabulary', 'country' => 'England', 'aliases' => 'Cambridgeshire' ),
	array( 'slug' => 'cheshire', 'name' => 'Cheshire Constabulary', 'country' => 'England', 'aliases' => 'Cheshire' ),
	array( 'slug' => 'city-of-london', 'name' => 'City of London Police', 'country' => 'England', 'aliases' => 'City of London' ),
	array( 'slug' => 'cleveland', 'name' => 'Cleveland Police', 'country' => 'England', 'aliases' => 'Cleveland' ),
	array( 'slug' => 'cumbria', 'name' => 'Cumbria Constabulary', 'country' => 'England', 'aliases' => 'Cumbria' ),
	array( 'slug' => 'derbyshire', 'name' => 'Derbyshire Constabulary', 'country' => 'England', 'aliases' => 'Derbyshire' ),
	array( 'slug' => 'devon-and-cornwall', 'name' => 'Devon and Cornwall Police', 'country' => 'England', 'aliases' => 'Devon & Cornwall,Devon and Cornwall' ),
	array( 'slug' => 'dorset', 'name' => 'Dorset Police', 'country' => 'England', 'aliases' => 'Dorset' ),
	array( 'slug' => 'durham', 'name' => 'Durham Constabulary', 'country' => 'England', 'aliases' => 'Durham' ),
	array( 'slug' => 'dyfed-powys', 'name' => 'Dyfed-Powys Police', 'country' => 'Wales', 'aliases' => 'Dyfed-Powys,Dyfed Powys' ),
	array( 'slug' => 'essex', 'name' => 'Essex Police', 'country' => 'England', 'aliases' => 'Essex' ),
	array( 'slug' => 'gloucestershire', 'name' => 'Gloucestershire Constabulary', 'country' => 'England', 'aliases' => 'Gloucestershire' ),
	array( 'slug' => 'greater-manchester', 'name' => 'Greater Manchester Police', 'country' => 'England', 'aliases' => 'GMP,Greater Manchester' ),
	array( 'slug' => 'gwent', 'name' => 'Gwent Police', 'country' => 'Wales', 'aliases' => 'Gwent' ),
	array( 'slug' => 'hampshire-and-isle-of-wight', 'name' => 'Hampshire and Isle of Wight Constabulary', 'country' => 'England', 'aliases' => 'Hampshire,Hampshire and Isle of Wight,Isle of Wight' ),
	array( 'slug' => 'hertfordshire', 'name' => 'Hertfordshire Constabulary', 'country' => 'England', 'aliases' => 'Hertfordshire' ),
	array( 'slug' => 'humberside', 'name' => 'Humberside Police', 'country' => 'England', 'aliases' => 'Humberside' ),
	array( 'slug' => 'kent', 'name' => 'Kent Police', 'country' => 'England', 'aliases' => 'Kent' ),
	array( 'slug' => 'lancashire', 'name' => 'Lancashire Constabulary', 'country' => 'England', 'aliases' => 'Lancashire' ),
	array( 'slug' => 'leicestershire', 'name' => 'Leicestershire Police', 'country' => 'England', 'aliases' => 'Leicestershire' ),
	array( 'slug' => 'lincolnshire', 'name' => 'Lincolnshire Police', 'country' => 'England', 'aliases' => 'Lincolnshire' ),
	array( 'slug' => 'merseyside', 'name' => 'Merseyside Police', 'country' => 'England', 'aliases' => 'Merseyside' ),
	array( 'slug' => 'metropolitan', 'name' => 'Metropolitan Police Service', 'country' => 'England', 'aliases' => 'Met Police,Met,Metropolitan Police,London' ),
	array( 'slug' => 'norfolk', 'name' => 'Norfolk Constabulary', 'country' => 'England', 'aliases' => 'Norfolk' ),
	array( 'slug' => 'north-wales', 'name' => 'North Wales Police', 'country' => 'Wales', 'aliases' => 'North Wales' ),
	array( 'slug' => 'north-yorkshire', 'name' => 'North Yorkshire Police', 'country' => 'England', 'aliases' => 'North Yorkshire' ),
	array( 'slug' => 'northamptonshire', 'name' => 'Northamptonshire Police', 'country' => 'England', 'aliases' => 'Northamptonshire' ),
	array( 'slug' => 'northumbria', 'name' => 'Northumbria Police', 'country' => 'England', 'aliases' => 'Northumbria' ),
	array( 'slug' => 'nottinghamshire', 'name' => 'Nottinghamshire Police', 'country' => 'England', 'aliases' => 'Nottinghamshire' ),
	array( 'slug' => 'south-wales', 'name' => 'South Wales Police', 'country' => 'Wales', 'aliases' => 'South Wales' ),
	array( 'slug' => 'south-yorkshire', 'name' => 'South Yorkshire Police', 'country' => 'England', 'aliases' => 'South Yorkshire' ),
	array( 'slug' => 'staffordshire', 'name' => 'Staffordshire Police', 'country' => 'England', 'aliases' => 'Staffordshire' ),
	array( 'slug' => 'suffolk', 'name' => 'Suffolk Constabulary', 'country' => 'England', 'aliases' => 'Suffolk' ),
	array( 'slug' => 'surrey', 'name' => 'Surrey Police', 'country' => 'England', 'aliases' => 'Surrey' ),
	array( 'slug' => 'sussex', 'name' => 'Sussex Police', 'country' => 'England', 'aliases' => 'Sussex' ),
	array( 'slug' => 'thames-valley', 'name' => 'Thames Valley Police', 'country' => 'England', 'aliases' => 'TVP,Thames Valley' ),
	array( 'slug' => 'warwickshire', 'name' => 'Warwickshire Police', 'country' => 'England', 'aliases' => 'Warwickshire' ),
	array( 'slug' => 'west-mercia', 'name' => 'West Mercia Police', 'country' => 'England', 'aliases' => 'West Mercia' ),
	array( 'slug' => 'west-midlands', 'name' => 'West Midlands Police', 'country' => 'England', 'aliases' => 'West Midlands' ),
	array( 'slug' => 'west-yorkshire', 'name' => 'West Yorkshire Police', 'country' => 'England', 'aliases' => 'West Yorkshire' ),
	array( 'slug' => 'wiltshire', 'name' => 'Wiltshire Police', 'country' => 'England', 'aliases' => 'Wiltshire' ),
);
