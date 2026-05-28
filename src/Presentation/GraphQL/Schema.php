<?php

declare(strict_types=1);

namespace ICMS\Presentation\GraphQL;

/**
 * GraphQL SDL schema for ICMS.
 * Used by the GraphQLServer to build the type system.
 *
 * Note: Sensitive PII fields (passport, EFNS data) are intentionally excluded from
 * query outputs and must be accessed through dedicated secure endpoints only.
 */
final class Schema
{
    public static function getSDL(): string
    {
        return <<<'SDL'
type Query {
    case(id: ID!): Case
    myCases(limit: Int, offset: Int): CaseList!
}

type Mutation {
    createCase(input: CreateCaseInput!): CreateCaseResult!
    updateCaseStatus(input: UpdateCaseStatusInput!): UpdateCaseStatusResult!
}

type Case {
    id: ID!
    status: String!
    referralSource: String!
    createdAt: String!
    updatedAt: String!
}

type CaseList {
    cases: [Case!]!
    total: Int!
    limit: Int!
    offset: Int!
}

input CreateCaseInput {
    referralSource: String!
}

type CreateCaseResult {
    ok: Boolean!
    caseId: ID
    error: String
}

input UpdateCaseStatusInput {
    caseId: ID!
    status: String!
}

type UpdateCaseStatusResult {
    ok: Boolean!
    caseId: ID
    status: String
    error: String
}
SDL;
    }
}
