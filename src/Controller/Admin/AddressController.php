<?php

declare(strict_types=1);

namespace SarissaOps\SyliusEnviatodoPlugin\Controller\Admin;

use SarissaOps\Enviatodo\Model\Address\Address;
use SarissaOps\SyliusEnviatodoPlugin\Controller\CsrfProtection;
use SarissaOps\SyliusEnviatodoPlugin\Shipping\EnviatodoAddressBook;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class AddressController
{
    public const CSRF_TOKEN_ID = 'enviatodo_address';

    public function __construct(
        private readonly EnviatodoAddressBook $addressBook,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    public function listAction(): JsonResponse
    {
        $addresses = [];
        foreach ($this->addressBook->all() as $address) {
            $addresses[] = $this->map($address);
        }

        return new JsonResponse(['addresses' => $addresses]);
    }

    public function showAction(Request $request, int $id): JsonResponse
    {
        $address = $this->addressBook->get($id);
        if (null === $address) {
            return new JsonResponse(['error' => 'not_found'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse(['address' => $this->map($address)]);
    }

    public function saveAction(Request $request): JsonResponse
    {
        $guard = CsrfProtection::validate($request, $this->csrfTokenManager, self::CSRF_TOKEN_ID);
        if (null !== $guard) {
            return $guard;
        }

        $data = json_decode($request->getContent(), true);
        if (!\is_array($data)) {
            return new JsonResponse(['error' => 'invalid_payload'], Response::HTTP_BAD_REQUEST);
        }
        $payload = [];
        foreach ($data as $key => $value) {
            if (\is_string($key)) {
                $payload[$key] = $value;
            }
        }

        try {
            $address = $this->addressBook->save($payload);
        } catch (\InvalidArgumentException) {
            return new JsonResponse(['error' => 'invalid_payload'], Response::HTTP_BAD_REQUEST);
        } catch (\RuntimeException) {
            return new JsonResponse(['error' => 'address_unavailable'], Response::HTTP_BAD_GATEWAY);
        }

        return new JsonResponse(['address' => $this->map($address)]);
    }

    public function deleteAction(Request $request, int $id): JsonResponse
    {
        $guard = CsrfProtection::validate($request, $this->csrfTokenManager, self::CSRF_TOKEN_ID);
        if (null !== $guard) {
            return $guard;
        }

        $this->addressBook->remove($id);

        return new JsonResponse(['deleted' => true]);
    }

    /** @return array<string, mixed> */
    private function map(Address $address): array
    {
        return [
            'id' => $address->getId(),
            'address_type_id' => $address->getAddressTypeId(),
            'full_name' => $address->getFullName(),
            'company' => $address->getCompany(),
            'street' => $address->getStreet(),
            'ext_number' => $address->getExtNumber(),
            'zip_code' => $address->getZipCode(),
            'suburb' => $address->getSuburb(),
            'municipality' => $address->getMunicipality(),
            'state' => $address->getState(),
            'state_code' => $address->getStateCode(),
            'country_code' => $address->getCountryCode(),
            'reference' => $address->getReference(),
            'default_addr' => $address->getDefaultAddr(),
        ];
    }
}
