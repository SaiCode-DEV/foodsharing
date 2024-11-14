# Routing

Routing happens completely through Symfony, using `#[Route]` attributes in the respective controllers.
(also, see `config/routes/api.yaml`)

To support old links, we still support the old routing scheme based on GET parameters.
This is handled in IndexController, based on data found in the deprecated Routing class.
