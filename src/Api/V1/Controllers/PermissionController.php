<?php

namespace Meva\Api\V1\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Meva\Api\V1\Requests\Permission\PermissionCreateRequest;
use Meva\Api\V1\Transformers\PermissionTransformer;
use Meva\Entities\Permission\Contracts\PermissionRepository;
use Meva\Entities\Permission\Models\Permission;
use Meva\Entities\User\Models\User;

class PermissionController extends Controller
{

    /**
     * Return all permissions
     * @param $id
     * @param PermissionRepository $permissionRepository
     * @return \Dingo\Api\Http\Response
     */
    public function show($id, PermissionRepository $permissionRepository)
    {
        return $this->response
            ->item($permissionRepository->find($id), new PermissionTransformer())
            ->setStatusCode(Response::HTTP_OK);
    }

    /**
     * Return all permissions
     * @param PermissionRepository $permissionRepository
     * @return \Dingo\Api\Http\Response
     */
    public function index(PermissionRepository $permissionRepository)
    {
        return $this->response
            ->collection($permissionRepository->all(), new PermissionTransformer())
            ->setStatusCode(Response::HTTP_OK);
    }

    /**
     * Get a JWT via given credentials.
     *
     * @param PermissionCreateRequest $permissionCreateRequest
     * @param PermissionRepository $permissionRepository
     * @return void
     */
    public function create(PermissionCreateRequest $permissionCreateRequest, PermissionRepository $permissionRepository)
    {
        $permissionRepository->create($permissionCreateRequest->all());
    }


    /**
     * Get a JWT via given credentials.
     *
     * @param Request $request
     * @param $id
     * @param PermissionRepository $permissionRepository
     * @return void
     */
    public function update(Request $request, $id, PermissionRepository $permissionRepository)
    {
        $permissionRepository->update($request->all(), $id);
    }
}
