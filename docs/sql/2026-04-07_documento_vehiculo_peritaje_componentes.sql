ALTER TABLE documento_vehiculo
    ADD COLUMN sistema_electrico_peritaje VARCHAR(255) NULL AFTER perito_peritaje,
    ADD COLUMN sistema_frenos_peritaje VARCHAR(255) NULL AFTER sistema_electrico_peritaje,
    ADD COLUMN sistema_direccion_peritaje VARCHAR(255) NULL AFTER sistema_frenos_peritaje,
    ADD COLUMN sistema_transmision_peritaje VARCHAR(255) NULL AFTER sistema_direccion_peritaje,
    ADD COLUMN sistema_suspension_peritaje VARCHAR(255) NULL AFTER sistema_transmision_peritaje,
    ADD COLUMN planta_motriz_peritaje VARCHAR(255) NULL AFTER sistema_suspension_peritaje,
    ADD COLUMN otros_peritaje VARCHAR(255) NULL AFTER planta_motriz_peritaje;
